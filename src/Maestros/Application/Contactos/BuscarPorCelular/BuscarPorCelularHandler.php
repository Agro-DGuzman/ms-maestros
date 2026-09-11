<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\BuscarPorCelular;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\ContactoRepository;

final readonly class BuscarPorCelularHandler implements RequestHandler
{
    public function __construct(private ContactoRepository $contactos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof BuscarPorCelular);

        $persona = $this->contactos->porCelular($peticion->celular);

        if ($persona === null) {
            return ResultWithValue::failure(Error::notFound(
                'CONTACTO_NO_ENCONTRADO',
                'No hay una persona de contacto con ese celular',
            ));
        }

        return ResultWithValue::of($persona->idDePersona());
    }
}
