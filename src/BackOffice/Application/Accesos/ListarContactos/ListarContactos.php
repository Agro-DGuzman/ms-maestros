<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ListarContactos;

use Core\Contracts\Request;
use Maestros\Application\Contactos\CriterioDeBusqueda;

final readonly class ListarContactos implements Request
{
    public function __construct(public CriterioDeBusqueda $criterio) {}
}
