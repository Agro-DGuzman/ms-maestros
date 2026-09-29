<?php

declare(strict_types=1);

namespace Maestros\Application\Propiedades\ListarPropiedades;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Propiedades\PropiedadesDeSocios;

/**
 * Llega acá solo si el socio está en el alcance de la persona: `AlcanceBehavior`
 * corta antes con 403. Un socio sin propiedades no es un error, es una lista
 * vacía.
 */
final readonly class ListarPropiedadesHandler implements RequestHandler
{
    public function __construct(private PropiedadesDeSocios $propiedades) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ListarPropiedades);

        return ResultWithValue::of($this->propiedades->deSocio($peticion->cardCode()));
    }
}
