<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ListarContactos;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Contactos\BuscadorDeContactos;

final readonly class ListarContactosHandler implements RequestHandler
{
    public function __construct(private BuscadorDeContactos $buscador) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ListarContactos);

        return ResultWithValue::of($this->buscador->buscar($peticion->criterio));
    }
}
