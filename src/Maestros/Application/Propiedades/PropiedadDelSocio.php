<?php

declare(strict_types=1);

namespace Maestros\Application\Propiedades;

/** Una propiedad tal como la lista la App al pedir una visita técnica. */
final readonly class PropiedadDelSocio
{
    public function __construct(
        public string $id,
        public string $nombre,
    ) {}
}
