<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class EnlacesCambiados
{
    /** @param list<CambioDeEnlace> $cambios vacía si no cambió nada */
    public function __construct(
        public int $idProducto,
        public ?string $itemCode,
        public array $cambios,
    ) {}
}
