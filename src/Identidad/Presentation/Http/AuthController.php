<?php

declare(strict_types=1);

namespace Identidad\Presentation\Http;

use App\Http\Envelope;
use App\Http\PersonaAutenticada;
use Closure;
use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Core\Results\ResultWithValue;
use DateTimeImmutable;
use DateTimeZone;
use Identidad\Application\Auth\CerrarSesion\CerrarSesion;
use Identidad\Application\Auth\IniciarSesion\IniciarSesion;
use Identidad\Application\Auth\RenovarSesion\RenovarSesion;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafio;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Desafios\IdDeDesafio;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;

/**
 * Los nombres y la forma de cada respuesta son los del contrato OpenAPI, contra
 * el que se construye la App (ver tests/Feature/ContratoDeRespuestasTest.php).
 * Adentro el concepto sigue siendo un desafío de ingreso; `otpId` es solo el
 * nombre en el cable.
 */
final readonly class AuthController
{
    public function __construct(
        private Mediator $mediator,
        private EcoDeCodigoDePrueba $eco,
    ) {}

    public function otp(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'telefono' => ['bail', 'required', 'string', 'max:20', self::celularBoliviano()],
            'canal' => ['nullable', 'in:whatsapp'],
        ]);

        $celular = Celular::desdeLocalBoliviano((string) $datos['telefono']);

        $resultado = $this->mediator->send(new SolicitarDesafio($celular));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);
        $id = $resultado->value();
        assert($id instanceof IdDeDesafio);

        // TEMPORAL: solo para números de prueba. Ver EcoDeCodigoDePrueba.
        $codigo = $this->eco->para($celular, $id);

        return Envelope::responder($resultado, ['otpId' => $id->value()]
            + ($codigo === null ? [] : ['codigoDePrueba' => $codigo]));
    }

    public function login(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'otpId' => ['required', 'uuid'],
            'codigo' => ['required', 'string', 'regex:/^\d{4}$/'],
            'dispositivo' => ['nullable', 'array'],
            'dispositivo.instalacionId' => ['required_with:dispositivo', 'uuid'],
            'dispositivo.plataforma' => ['required_with:dispositivo', 'in:android,ios'],
        ]);

        /** @var array{instalacionId?: string, plataforma?: string} $dispositivo */
        $dispositivo = $datos['dispositivo'] ?? [];

        $resultado = $this->mediator->send(new IniciarSesion(
            IdDeDesafio::desde((string) $datos['otpId']),
            (string) $datos['codigo'],
            $dispositivo['instalacionId'] ?? null,
            $dispositivo['plataforma'] ?? null,
        ));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);

        /** @var array{token: TokenEmitido, sesion: SesionDeAplicacion, contexto: ContextoDeContacto} $salida */
        $salida = $resultado->value();
        $sesion = $salida['sesion'];

        return Envelope::responder($resultado, [
            'sesionId' => $sesion->idDeSesion()->value(),
            'tokens' => self::tokens($salida['token']),
            'contexto' => $salida['contexto']->aArray(),
            'iniciadaEn' => self::instante($sesion->iniciadaEn()),
            'expiraEn' => self::instante($sesion->expiraEn()),
            // No hay registro push propio: lo que se guarda es la instalación.
            'dispositivoRegistrado' => $sesion->dispositivo() !== null,
        ]);
    }

    public function refresh(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate(['refreshToken' => ['required', 'string']]);

        $resultado = $this->mediator->send(new RenovarSesion((string) $datos['refreshToken']));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);
        $token = $resultado->value();
        assert($token instanceof TokenEmitido);

        return Envelope::responder($resultado, self::tokens($token));
    }

    public function logout(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'refreshToken' => ['nullable', 'string'],
            'instalacionId' => ['nullable', 'uuid'],
        ]);

        $resultado = $this->mediator->send(new CerrarSesion(
            PersonaAutenticada::deLaPeticion($peticion),
            isset($datos['refreshToken']) ? (string) $datos['refreshToken'] : null,
            isset($datos['instalacionId']) ? (string) $datos['instalacionId'] : null,
        ));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);

        return Envelope::responder($resultado, ['sesionCerrada' => (bool) $resultado->value()]);
    }

    /** @return array{accessToken: string, tokenType: string, expiraEnSegundos: int, refreshToken: string} */
    private static function tokens(TokenEmitido $token): array
    {
        return [
            'accessToken' => $token->accessToken,
            'tokenType' => 'Bearer',
            'expiraEnSegundos' => $token->expiraEnSegundos,
            'refreshToken' => $token->refreshToken,
        ];
    }

    private static function instante(DateTimeImmutable $momento): string
    {
        return $momento->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * El celular se valida con la misma regla del dominio, pero acá: así un
     * número mal escrito es un error del campo `telefono`, que la App puede
     * marcar, y no un error suelto.
     */
    private static function celularBoliviano(): Closure
    {
        return static function (string $atributo, mixed $valor, Closure $fallar): void {
            try {
                Celular::desdeLocalBoliviano(is_string($valor) ? $valor : '');
            } catch (DomainException) {
                $fallar('No es un celular boliviano.');
            }
        };
    }
}
