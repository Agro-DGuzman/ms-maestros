<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/** Lo que hace falta para asentar el cambio: qué producto, y de qué a qué. */
final readonly class DescripcionCambiada
{
    public function __construct(
        public int $idProducto,
        public ?string $itemCode,
        public ?string $anterior,
        public ?string $nuevo,
    ) {}

    public function cambio(): bool
    {
        return $this->anterior !== $this->nuevo;
    }
}
