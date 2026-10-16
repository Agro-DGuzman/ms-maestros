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
        public bool $dadoDeBaja,
        /** `origen_esquema` no vacío: lo que nunca llegó de SAP lo cargó la web. */
        public bool $llegoDeSap,
        /** La carga el back-office; SAP no la manda (P5). */
        public ?string $descripcion,
        public EnlacesDeProducto $enlaces,
    ) {}

    /**
     * La misma regla que `EloquentCatalogoDeProductos::visibles()`, dicha en
     * palabras: el primer motivo que la deja afuera.
     *
     * Solo nombra a SAP cuando el producto llegó de SAP: la baja también la
     * puede poner el comando de la transición, y un producto de la web que
     * está inactivo nunca pasó por SAP.
     */
    public function porQueNoSeVe(): ?string
    {
        return match (true) {
            $this->dadoDeBaja => 'Está dado de baja.',
            ! $this->activo => $this->llegoDeSap ? 'Está inactivo en SAP.' : 'Está inactivo.',
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
