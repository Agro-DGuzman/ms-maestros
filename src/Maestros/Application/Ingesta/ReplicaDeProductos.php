<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use DateTimeImmutable;

/**
 * El lado del catálogo que escribe la ingesta. No hay repositorio de productos
 * en el dominio: el catálogo es una tabla que varios dueños escriben por
 * columnas distintas, y este puerto es el que solo toca las de SAP.
 */
interface ReplicaDeProductos
{
    public function estado(string $itemCode): ?EstadoDeProductoReplicado;

    public function replicar(ProductoIngresado $producto): void;

    public function darDeBaja(string $itemCode, DateTimeImmutable $momento): void;
}
