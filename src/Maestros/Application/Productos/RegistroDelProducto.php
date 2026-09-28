<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/** Ante SENASAG, INIAF u `otro`: los tres valores del contrato. */
final readonly class RegistroDelProducto
{
    public function __construct(
        public string $entidad,
        public string $numero,
    ) {}
}
