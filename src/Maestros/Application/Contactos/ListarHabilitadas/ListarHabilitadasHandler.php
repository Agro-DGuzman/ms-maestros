<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ListarHabilitadas;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;

final readonly class ListarHabilitadasHandler implements RequestHandler
{
    public function __construct(private ContactoRepository $contactos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ListarHabilitadas);

        return ResultWithValue::of(array_map(
            static fn (PersonaDeContacto $p): IdDePersona => $p->idDePersona(),
            $this->contactos->habilitadas(),
        ));
    }
}
