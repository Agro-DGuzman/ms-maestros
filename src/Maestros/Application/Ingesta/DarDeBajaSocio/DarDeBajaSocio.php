<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\DarDeBajaSocio;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use DateTimeImmutable;
use Maestros\Domain\Socios\CodigoDeSocio;

final readonly class DarDeBajaSocio implements EscrituraDeMaquina, Request, RequiereTransaccion
{
    public function __construct(
        public CodigoDeSocio $socio,
        public DateTimeImmutable $momento,
    ) {}
}
