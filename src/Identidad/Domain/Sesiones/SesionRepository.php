<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Contracts\Repository;
use Maestros\Domain\Contactos\IdDePersona;

interface SesionRepository extends Repository
{
    /** @return list<SesionDeAplicacion> */
    public function abiertasDe(IdDePersona $persona): array;
}
