<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ReplicarSocio;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use DateTimeImmutable;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\SocioIngresado;

/**
 * `momento` es cuándo llegó el envío. Lo trae la petición, y no un reloj,
 * porque el único reloj inyectable es de Identidad y Maestros no la conoce.
 */
final readonly class ReplicarSocio implements EscrituraDeMaquina, Request, RequiereTransaccion
{
    public function __construct(
        public OperacionDeIngesta $operacion,
        public ?string $cardCodeDeLaRuta,
        public SocioIngresado $socio,
        public DateTimeImmutable $momento,
    ) {}
}
