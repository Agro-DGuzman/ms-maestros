<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Contracts\Repository;

interface SocioRepository extends Repository
{
    /**
     * Los que ms-comercial tiene que tener: con línea, que deja afuera a la
     * semilla, y sin baja. Inactivos incluidos: comercial no decide quién
     * los ve (EM5).
     *
     * @return list<Socio>
     */
    public function conLineaYSinBaja(): array;
}
