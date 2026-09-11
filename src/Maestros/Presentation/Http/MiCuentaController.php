<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use App\Http\PersonaAutenticada;
use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;

final readonly class MiCuentaController
{
    public function __construct(private Mediator $mediator) {}

    public function __invoke(Request $peticion): JsonResponse
    {
        $resultado = $this->mediator->send(
            new ObtenerContexto(PersonaAutenticada::deLaPeticion($peticion)),
        );

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);
        $contexto = $resultado->value();
        assert($contexto instanceof ContextoDeContacto);

        return Envelope::responder($resultado, $contexto->aArray());
    }
}
