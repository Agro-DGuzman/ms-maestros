<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Contracts\Repository;
use Maestros\Domain\Grupos\IdDeGrupo;

interface SocioRepository extends Repository
{
    /** @return list<Socio> */
    public function porGrupo(IdDeGrupo $grupo): array;
}
