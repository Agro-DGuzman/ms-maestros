<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class CategoriaDelCatalogo
{
    public function __construct(
        public string $codigo,
        public string $nombre,
    ) {}
}
