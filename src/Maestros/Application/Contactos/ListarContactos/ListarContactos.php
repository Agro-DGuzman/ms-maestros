<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ListarContactos;

use Core\Contracts\Request;
use Maestros\Application\Contactos\CriterioDeBusqueda;

/**
 * No implementa `ConAlcanceDeSocio`: la lista es del operador del back-office,
 * que ve a todos los socios. El alcance restringe lo que ve un socio de otros
 * socios, y acá no hay socio autenticado.
 */
final readonly class ListarContactos implements Request
{
    public function __construct(public CriterioDeBusqueda $criterio) {}
}
