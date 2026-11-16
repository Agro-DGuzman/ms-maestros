<?php

declare(strict_types=1);

namespace Identidad\Application\Push\RegistrarPreferencias;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use DateTimeImmutable;

/** La foto de las preferencias de una persona, tal como la publicó ms-comercial. */
final readonly class RegistrarPreferencias implements Request, RequiereTransaccion
{
    public function __construct(
        public string $persona,
        public bool $facturas,
        public bool $pagos,
        public bool $reportes,
        public bool $catalogo,
        public DateTimeImmutable $ocurridoEn,
    ) {}
}
