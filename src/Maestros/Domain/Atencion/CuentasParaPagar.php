<?php

declare(strict_types=1);

namespace Maestros\Domain\Atencion;

/** Las cuentas de Agropartners donde el socio deposita, y a nombre de quién están. */
final readonly class CuentasParaPagar
{
    /** @param list<CuentaBancaria> $cuentas */
    public function __construct(
        public array $cuentas,
        public string $razonSocialDelTitular,
        public string $nitDelTitular,
    ) {}
}
