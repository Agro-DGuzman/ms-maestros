<?php

declare(strict_types=1);

namespace Identidad\Presentation\Http;

use App\Http\Envelope;
use Core\Contracts\Mediator;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafio;
use Identidad\Domain\Desafios\IdDeDesafio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Domain\Contactos\Celular;

final readonly class AuthController
{
    public function __construct(private Mediator $mediator) {}

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

        $id = $resultado->value();
        assert($id instanceof IdDeDesafio);

        return Envelope::responder($resultado, ['idDeDesafio' => $id->value()]);
    }
}
