<?php

declare(strict_types=1);

namespace Maestros\Domain\Atencion;

final readonly class CuentaBancaria
{
    public function __construct(
        public string $banco,
        public string $numeroCuenta,
    ) {}
}
