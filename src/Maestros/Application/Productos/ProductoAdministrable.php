<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * Un producto tal como lo ve quien administra el catálogo: también los que la
 * App todavía no muestra, y por qué no los muestra.
 */
final readonly class ProductoAdministrable
{
    public function __construct(
        public int $id,
        public ?string $itemCode,
        public string $nombre,
        /** El nombre de la categoría; null si falta o apunta a una que no existe. */
        public ?string $categoria,
        public bool $activo,
        public EnlacesDeProducto $enlaces,
    ) {}

    /**
     * La misma regla que `EloquentCatalogoDeProductos::visibles()`, dicha en
     * palabras: el primer motivo que la deja afuera.
     */
    public function porQueNoSeVe(): ?string
    {
        return match (true) {
            ! $this->activo => 'Está dado de baja.',
            $this->itemCode === null => 'No tiene código de artículo.',
            $this->categoria === null => 'No tiene categoría.',
            default => null,
        };
    }

    public function visibleEnApp(): bool
    {
        return $this->porQueNoSeVe() === null;
    }
}
