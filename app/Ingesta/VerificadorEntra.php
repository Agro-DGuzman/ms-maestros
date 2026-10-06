<?php

declare(strict_types=1);

namespace App\Ingesta;

use Core\Results\Error;
use Core\Results\Result;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

/**
 * El token de una identidad de máquina (el Sincronizador), emitido por Entra
 * ID con client credentials. No hay persona detrás: lo que autoriza es el rol.
 *
 * Es otro verificador que el de Keycloak a propósito: otro emisor, otra
 * audiencia, y nada de `preferred_username`.
 */
final readonly class VerificadorEntra
{
    private const int MINUTOS_DE_CACHE = 60;

    private const int MINUTOS_ENTRE_REFETCH = 5;

    public function __construct(
        private Http $http,
        private Cache $cache,
    ) {}

    public function verificar(string $jwt): Result
    {
        $tenant = Config::string('ingesta.entra.tenant');
        $audiencia = Config::string('ingesta.entra.audiencia');

        if (trim($tenant) === '' || trim($audiencia) === '') {
            Log::error('ingesta: falta INGESTA_ENTRA_TENANT_ID o INGESTA_ENTRA_AUDIENCIA');

            return Result::failure(Error::problem('CONFIGURACION_INCOMPLETA', 'Falta configurar la validación del token de ingesta'));
        }

        $kid = self::kid($jwt);

        if ($kid === null) {
            return self::rechazar('formato');
        }

        $claves = $this->claves($tenant, refrescar: false);

        // Entra rota claves: una que el JWKS cacheado no tiene se busca otra
        // vez, pero no más de una vez cada 5 minutos, para que un token con un
        // kid inventado no se convierta en una llamada a Entra por pedido.
        if (is_array($claves) && ! self::conoce($claves, $kid) && $this->puedeRefrescar($tenant)) {
            $claves = $this->claves($tenant, refrescar: true);
        }

        if (! is_array($claves)) {
            return Result::failure(Error::problem('ENTRA_NO_DISPONIBLE', 'No se pudieron obtener las claves de Entra ID'));
        }

        // La tolerancia de reloj es global en la librería: se restaura para no
        // cambiarle el criterio al verificador de Keycloak.
        $previo = JWT::$leeway;
        JWT::$leeway = 60;

        try {
            $claims = JWT::decode($jwt, JWK::parseKeySet($claves, 'RS256'));
        } catch (ExpiredException|BeforeValidException) {
            return self::rechazar('vencido');
        } catch (SignatureInvalidException) {
            return self::rechazar('firma');
        } catch (Throwable) {
            return self::rechazar('formato');
        } finally {
            JWT::$leeway = $previo;
        }

        if (($claims->iss ?? null) !== "https://login.microsoftonline.com/{$tenant}/v2.0") {
            return self::rechazar('iss');
        }

        if (! in_array($audiencia, (array) ($claims->aud ?? []), true)) {
            return self::rechazar('aud');
        }

        if (! self::tieneElRol($claims, Config::string('ingesta.entra.rol'))) {
            return self::rechazar('rol');
        }

        return Result::success();
    }

    /** @return array<mixed>|null el JWKS, o null si Entra no respondió */
    private function claves(string $tenant, bool $refrescar): ?array
    {
        $llave = "ingesta:entra:jwks:{$tenant}";

        if ($refrescar) {
            $this->cache->forget($llave);
            $this->cache->put("ingesta:entra:refetch:{$tenant}", true, now()->addMinutes(self::MINUTOS_ENTRE_REFETCH));
        }

        $guardado = $this->cache->get($llave);

        if (is_array($guardado)) {
            return $guardado;
        }

        try {
            $respuesta = $this->http
                ->timeout(Config::integer('ingesta.entra.timeout', 10))
                ->get("https://login.microsoftonline.com/{$tenant}/discovery/v2.0/keys");
        } catch (Throwable) {
            return null;
        }

        $cuerpo = $respuesta->json();

        if (! $respuesta->successful() || ! is_array($cuerpo) || ! is_array($cuerpo['keys'] ?? null)) {
            return null;
        }

        // Un JWKS que no se pudo pedir no se cachea: el próximo pedido reintenta.
        $this->cache->put($llave, $cuerpo, now()->addMinutes(self::MINUTOS_DE_CACHE));

        return $cuerpo;
    }

    private function puedeRefrescar(string $tenant): bool
    {
        return ! $this->cache->has("ingesta:entra:refetch:{$tenant}");
    }

    /** @param array<mixed> $jwks */
    private static function conoce(array $jwks, string $kid): bool
    {
        foreach ((array) ($jwks['keys'] ?? []) as $clave) {
            if (is_array($clave) && ($clave['kid'] ?? null) === $kid) {
                return true;
            }
        }

        return false;
    }

    /** El `kid` de la cabecera, leído sin verificar: solo para elegir la clave. */
    private static function kid(string $jwt): ?string
    {
        $partes = explode('.', $jwt);

        if (count($partes) !== 3) {
            return null;
        }

        $cabecera = json_decode((string) base64_decode(strtr($partes[0], '-_', '+/'), true), true);
        $kid = is_array($cabecera) ? ($cabecera['kid'] ?? null) : null;

        return is_string($kid) && $kid !== '' ? $kid : null;
    }

    private static function tieneElRol(stdClass $claims, string $rol): bool
    {
        $roles = $claims->roles ?? null;

        return is_array($roles) && in_array($rol, $roles, true);
    }

    /**
     * Un 401 de configuración en Entra y uno de un atacante se ven iguales
     * desde afuera: el motivo en el log es lo que los distingue.
     */
    private static function rechazar(string $motivo): Result
    {
        Log::warning('ingesta: token rechazado', ['motivo' => $motivo]);

        return Result::failure(Error::failure('TOKEN_INVALIDO', 'El token de ingesta no es válido'));
    }
}
