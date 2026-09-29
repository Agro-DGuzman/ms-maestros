<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use App\Http\PersonaAutenticada;
use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Application\Alcance\AlcanceErrors;
use Maestros\Application\Propiedades\ListarPropiedades\ListarPropiedades;
use Maestros\Application\Propiedades\PropiedadDelSocio;
use Maestros\Domain\Socios\CodigoDeSocio;

/** El paso 2 de *Solicitar visita técnica*: el `id` es el que viaja a ms-comercial. */
final readonly class PropiedadesController
{
    public function __construct(private Mediator $mediator) {}

    public function __invoke(Request $peticion, string $cardCode): JsonResponse
    {
        // Un código que ni siquiera es válido es un socio fuera de alcance:
        // el contrato no documenta 400 para esta ruta, y distinguirlo diría
        // qué forma tienen los códigos que sí existen.
        try {
            $socio = CodigoDeSocio::desde($cardCode);
        } catch (DomainException) {
            return Envelope::responder(Result::failure(AlcanceErrors::accesoDenegado()));
        }

        $resultado = $this->mediator->send(
            new ListarPropiedades(PersonaAutenticada::deLaPeticion($peticion), $socio),
        );

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        assert($resultado instanceof ResultWithValue);
        $propiedades = $resultado->value();
        assert(is_array($propiedades));

        $items = [];

        foreach ($propiedades as $propiedad) {
            assert($propiedad instanceof PropiedadDelSocio);
            $items[] = ['id' => $propiedad->id, 'nombre' => $propiedad->nombre];
        }

        return Envelope::responder($resultado, ['items' => $items]);
    }
}
