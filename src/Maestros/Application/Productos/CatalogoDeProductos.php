<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * Lo que la App puede ver del catálogo. Un producto es visible si está activo,
 * tiene código de artículo y tiene categoría: el contrato identifica cada uno
 * por su itemCode y exige la categoría en cada tarjeta. Lo que no cumple sigue
 * en la tabla, cargándose, pero para la App no existe.
 */
interface CatalogoDeProductos
{
    /**
     * Las que tienen al menos un producto visible: son chips, y uno que deja
     * la pantalla vacía no le sirve a nadie.
     *
     * @return list<CategoriaDelCatalogo>
     */
    public function categorias(): array;

    /** Cualquier categoría cargada, tenga productos o no: filtrar por ella no es un error. */
    public function existeCategoria(string $codigo): bool;

    public function pagina(?string $codigoDeCategoria, int $pagina, int $tamanoDePagina): PaginaDeProductos;

    public function producto(string $itemCode): ?ProductoDelCatalogo;
}
