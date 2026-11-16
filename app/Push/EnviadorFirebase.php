<?php

declare(strict_types=1);

namespace App\Push;

use Firebase\JWT\JWT;
use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\ResultadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

/**
 * Firebase Cloud Messaging, API HTTP v1 (NP1). El token de acceso de Google
 * se pide firmando un JWT con la cuenta de servicio, con firebase/php-jwt,
 * que ya es dependencia: no hace falta una librería para un pedido HTTP
 * (NP21). Corre solo en el `worker`, que es el único que tiene la credencial.
 *
 * Cada respuesta se traduce a qué hacer según la referencia de errores de
 * FCM v1 (NP20), y el motivo de un rechazo va al log sin el token (NP16).
 */
final readonly class EnviadorFirebase implements EnviadorDePush
{
    private const string URL_DEL_TOKEN = 'https://oauth2.googleapis.com/token';

    private const string ALCANCE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const string CLAVE_DE_CACHE = 'push.firebase.token-de-acceso';

    /** El token se guarda hasta cinco minutos antes de vencer. */
    private const int MARGEN = 300;

    /** Firebase pide esperar al menos un minuto después de una cuota excedida. */
    private const int ESPERA_POR_CUOTA = 60;

    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $proyecto,
        private string $cuentaDeServicio,
        private LoggerInterface $log = new NullLogger,
    ) {}

    public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio
    {
        try {
            $acceso = $this->tokenDeAcceso();
        } catch (Throwable $e) {
            $this->log->error('Firebase: no se pudo obtener el token de acceso', [
                'avisoId' => $aviso->avisoId,
                'error' => self::sinToken($e->getMessage(), $token),
            ]);

            return ResultadoDeEnvio::reintentarEn(null);
        }

        try {
            $respuesta = $this->http->withToken($acceso)->timeout(10)->post(
                "https://fcm.googleapis.com/v1/projects/{$this->proyecto}/messages:send",
                ['message' => self::mensaje($token, $aviso)],
            );
        } catch (Throwable $e) {
            $this->log->warning('Firebase no respondió', [
                'avisoId' => $aviso->avisoId,
                'error' => self::sinToken($e->getMessage(), $token),
            ]);

            return ResultadoDeEnvio::reintentarEn(null);
        }

        if ($respuesta->successful()) {
            return ResultadoDeEnvio::entregado();
        }

        return $this->clasificar($respuesta, $token, $aviso);
    }

    private function clasificar(Response $respuesta, TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio
    {
        $status = $respuesta->status();
        $codigo = self::codigo($respuesta);
        $mensaje = $respuesta->json('error.message');

        $this->log->error('Firebase rechazó el push', [
            'avisoId' => $aviso->avisoId,
            'status' => $status,
            'codigo' => $codigo,
            'mensaje' => is_string($mensaje) ? self::sinToken($mensaje, $token) : null,
        ]);

        // Un 401 puede ser nuestro token de acceso vencido o revocado: se pide otro la próxima vez.
        if ($status === 401) {
            $this->cache->forget(self::CLAVE_DE_CACHE);
        }

        $reintentoIndicado = self::retryAfter($respuesta);

        return match (true) {
            // Solo UNREGISTERED dice que el token murió: un proyecto mal escrito también da 404,
            // y borrar ahí dejaría sin avisos a cada persona alcanzada.
            $codigo === 'UNREGISTERED' => ResultadoDeEnvio::tokenMuerto(),
            $status === 429 => ResultadoDeEnvio::reintentarEn(max(self::ESPERA_POR_CUOTA, $reintentoIndicado ?? 0)),
            $status >= 500 => ResultadoDeEnvio::reintentarEn($reintentoIndicado),
            // UNAUTHENTICATED sin THIRD_PARTY_AUTH_ERROR es nuestro token de acceso: con otro, puede pasar.
            $status === 401 && $codigo !== 'THIRD_PARTY_AUTH_ERROR' => ResultadoDeEnvio::reintentarEn(null),
            default => ResultadoDeEnvio::rechazado($codigo ?? "HTTP {$status}"),
        };
    }

    /** @return array<string, mixed> */
    private static function mensaje(TokenPush $token, AvisoParaPush $aviso): array
    {
        return [
            'token' => $token->valor(),
            'notification' => ['title' => $aviso->titulo, 'body' => $aviso->cuerpo],
            // Firebase solo acepta texto en `data`: un null va como cadena vacía.
            'data' => [
                'avisoId' => $aviso->avisoId,
                'categoria' => $aviso->categoria->value,
                'cardCode' => $aviso->cardCode ?? '',
            ],
            'android' => ['priority' => 'high'],
        ];
    }

    private function tokenDeAcceso(): string
    {
        $guardado = $this->cache->get(self::CLAVE_DE_CACHE);

        if (is_string($guardado) && $guardado !== '') {
            return $guardado;
        }

        $cuenta = json_decode($this->cuentaDeServicio, true);
        $correo = is_array($cuenta) ? ($cuenta['client_email'] ?? null) : null;
        $clave = is_array($cuenta) ? ($cuenta['private_key'] ?? null) : null;

        if (! is_string($correo) || ! is_string($clave)) {
            throw new RuntimeException('La cuenta de servicio de Firebase no trae client_email y private_key');
        }

        $ahora = time();
        $asercion = JWT::encode([
            'iss' => $correo,
            'sub' => $correo,
            'aud' => self::URL_DEL_TOKEN,
            'scope' => self::ALCANCE,
            'iat' => $ahora,
            'exp' => $ahora + 3600,
        ], $clave, 'RS256');

        $respuesta = $this->http->asForm()->timeout(10)->post(self::URL_DEL_TOKEN, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $asercion,
        ]);

        $token = $respuesta->json('access_token');

        if (! $respuesta->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException("Google respondió {$respuesta->status()} al pedir el token de acceso");
        }

        $vence = $respuesta->json('expires_in');
        $this->cache->put(self::CLAVE_DE_CACHE, $token, max(60, (is_int($vence) ? $vence : 3600) - self::MARGEN));

        return $token;
    }

    /** El código está en los detalles de FCM; si no, el estado de Google (`UNAVAILABLE`, `INTERNAL`). */
    private static function codigo(Response $respuesta): ?string
    {
        $detalles = $respuesta->json('error.details');

        if (is_array($detalles)) {
            foreach ($detalles as $detalle) {
                if (is_array($detalle) && is_string($detalle['errorCode'] ?? null)) {
                    return $detalle['errorCode'];
                }
            }
        }

        $estado = $respuesta->json('error.status');

        return is_string($estado) ? $estado : null;
    }

    private static function retryAfter(Response $respuesta): ?int
    {
        $valor = $respuesta->header('Retry-After');

        return is_numeric($valor) ? (int) $valor : null;
    }

    /** El texto que vuelve —de Google o de la red— no está bajo nuestro control: si repite el token, se tapa. */
    private static function sinToken(string $texto, TokenPush $token): string
    {
        return str_replace($token->valor(), '[token push]', $texto);
    }
}
