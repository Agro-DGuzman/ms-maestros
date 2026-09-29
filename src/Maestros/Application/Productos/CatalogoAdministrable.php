<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * El lado del catálogo que se administra desde el back-office. Solo toca los
 * enlaces: lo demás viene de SAP o de script.
 */
interface CatalogoAdministrable
{
    /** @return list<ProductoAdministrable> por nombre */
    public function listar(?string $texto, FiltroDeEnlaces $filtro): array;

    public function buscar(int $id): ?ProductoAdministrable;

    public function guardarEnlaces(int $id, EnlacesDeProducto $enlaces): void;
}
