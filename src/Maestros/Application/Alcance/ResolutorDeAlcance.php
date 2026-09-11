<?php

declare(strict_types=1);

namespace Maestros\Application\Alcance;

use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;

interface ResolutorDeAlcance
{
    public function alcanza(IdDePersona $persona, CodigoDeSocio $socio): bool;
}
