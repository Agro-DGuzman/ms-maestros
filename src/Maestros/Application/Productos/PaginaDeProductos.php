<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class PaginaDeProductos
{
    /** @param list<ProductoDelCatalogo> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $pagina,
        public int $tamanoDePagina,
    ) {}

    public function totalDePaginas(): int
    {
        return (int) ceil($this->total / $this->tamanoDePagina);
    }
}
