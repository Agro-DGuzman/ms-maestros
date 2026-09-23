<?php

declare(strict_types=1);

namespace Identidad\Presentation\Http;

use App\Http\Envelope;
use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use Identidad\Application\Auth\CerrarSesion\CerrarSesion;
use Identidad\Application\Auth\IniciarSesion\IniciarSesion;
use Identidad\Application\Auth\RenovarSesion\RenovarSesion;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafio;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Desafios\IdDeDesafio;
use Identidad\Domain\Sesiones\IdDeSesion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;

final readonly class AuthController
{
    public function __construct(
        private Mediator $mediator,
        private EcoDeCodigoDePrueba $eco,
    ) {}

    public function otp(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'telefono' => ['required', 'string', 'max:20'],
        ]);

        // El objeto de valor valida lo que solo se puede validar DESPUÉS de
        // normalizar; el FormRequest, lo de antes. Nada se duplica.
        $celular = Celular::desdeLocalBoliviano((string) $datos['telefono']);

        $resultado = $this->mediator->send(new SolicitarDesafio($celular));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);
        $id = $resultado->value();
        assert($id instanceof IdDeDesafio);

        // `otpId` es el nombre del contrato OpenAPI, contra el que se construye la
        // App. Adentro el concepto sigue siendo un desafío de ingreso: esto es
        // solo el nombre en el cable.
        // TEMPORAL: solo para números de prueba. Ver EcoDeCodigoDePrueba.
        $codigo = $this->eco->para($celular, $id);

        return Envelope::responder($resultado, ['otpId' => $id->value()]
            + ($codigo === null ? [] : ['codigoDePrueba' => $codigo]));
    }

    public function login(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'otpId' => ['required', 'string', 'max:40'],
            'codigo' => ['required', 'string', 'size:4'],
            'instalacionId' => ['nullable', 'string', 'max:80'],
            'plataforma' => ['nullable', 'string', 'in:android,ios'],
        ]);

        $resultado = $this->mediator->send(new IniciarSesion(
            IdDeDesafio::desde((string) $datos['otpId']),
            (string) $datos['codigo'],
            isset($datos['instalacionId']) ? (string) $datos['instalacionId'] : null,
            isset($datos['plataforma']) ? (string) $datos['plataforma'] : null,
        ));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);

        /** @var array{token: TokenEmitido, sesion: IdDeSesion, contexto: ContextoDeContacto} $salida */
        $salida = $resultado->value();

        return Envelope::responder($resultado, [
            'token' => $salida['token']->accessToken,
            'refreshToken' => $salida['token']->refreshToken,
            'expiraEnSegundos' => $salida['token']->expiraEnSegundos,
            'idDeSesion' => $salida['sesion']->value(),
            'contexto' => $salida['contexto']->aArray(),
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

        return Envelope::responder($resultado, [
            'token' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'expiraEnSegundos' => $token->expiraEnSegundos,
        ]);
    }

    public function logout(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate(['refreshToken' => ['required', 'string']]);

        return Envelope::responder(
            $this->mediator->send(new CerrarSesion((string) $datos['refreshToken'])),
        );
    }
}
