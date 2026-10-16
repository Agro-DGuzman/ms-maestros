<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * El lado del catálogo que se administra desde el back-office: los cuatro
 * enlaces y la descripción. El resto de la ficha viene de SAP por la ingesta.
 */
interface CatalogoAdministrable
{
    /** @return list<ProductoAdministrable> por nombre */
    public function listar(?string $texto, FiltroDeEnlaces $filtro): array;

    public function buscar(int $id): ?ProductoAdministrable;

    public function guardarEnlaces(int $id, EnlacesDeProducto $enlaces): void;

    public function guardarDescripcion(int $id, ?string $descripcion): void;
}
