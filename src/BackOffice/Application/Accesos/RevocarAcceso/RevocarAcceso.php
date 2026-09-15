<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\RevocarAcceso;

use BackOffice\Domain\Operadores\Operador;
use Core\Contracts\Request;

final readonly class RevocarAcceso implements Request
{
    public function __construct(
        public string $idDePersona,
        public Operador $operador,
        public string $direccionIp,
    ) {}
}
