<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Alcance;

use Maestros\Application\Alcance\ResolutorDeAlcance;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;
use RuntimeException;

/** Doble para pruebas. Se niega a existir en producción. */
final class ResolutorQueNiegaTodo implements ResolutorDeAlcance
{
    public function __construct(string $entorno)
    {
        if ($entorno === 'production') {
            throw new RuntimeException('ResolutorQueNiegaTodo no puede usarse en producción');
        }
    }

    public function alcanza(IdDePersona $persona, CodigoDeSocio $socio): bool
    {
        return false;
    }
}
