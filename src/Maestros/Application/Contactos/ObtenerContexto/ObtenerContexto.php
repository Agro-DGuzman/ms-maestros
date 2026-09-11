<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ObtenerContexto;

use Core\Contracts\Request;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class ObtenerContexto implements Request
{
    public function __construct(public IdDePersona $persona) {}
}
