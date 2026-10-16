<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\DarDeBajaProducto;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use DateTimeImmutable;

/**
 * `momento` es cuándo llegó el envío. Lo trae la petición, y no un reloj,
 * porque el único reloj inyectable es de Identidad y Maestros no la conoce.
 */
final readonly class DarDeBajaProducto implements EscrituraDeMaquina, Request, RequiereTransaccion
{
    public function __construct(
        public string $itemCode,
        public DateTimeImmutable $momento,
    ) {}
}
