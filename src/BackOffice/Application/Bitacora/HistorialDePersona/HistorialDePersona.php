<?php

declare(strict_types=1);

namespace BackOffice\Application\Bitacora\HistorialDePersona;

use Core\Contracts\Request;

final readonly class HistorialDePersona implements Request
{
    public function __construct(public string $idDePersona) {}
}
