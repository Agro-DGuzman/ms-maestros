<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/**
 * Un producto que la App ve por primera vez y que se acaba de marcar como
 * anunciado. La categoría lleva el nombre guardado, que la ingesta no pisa,
 * y no el que trajo el envío.
 */
final readonly class ProductoAnunciado
{
    public function __construct(
        public string $itemCode,
        public string $nombre,
        public string $categoriaCodigo,
        public string $categoriaNombre,
    ) {}
}
