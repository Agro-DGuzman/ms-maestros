<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\HabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class HabilitarPersona implements Request, RequiereTransaccion
{
    public function __construct(public IdDePersona $persona) {}
}
