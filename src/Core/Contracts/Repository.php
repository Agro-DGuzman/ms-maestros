<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Domain\AggregateRoot;

interface Repository
{
    public function find(EntityId $id): ?AggregateRoot;

    public function add(AggregateRoot $agregado): void;

    /**
     * Reemplaza el agregado si ya existe, lo crea si no.
     * Hace falta porque las réplicas son inmutables: no hay nada que mutar
     * entre cargar y confirmar, y una réplica que vuelve a llegar reemplaza.
     */
    public function save(AggregateRoot $agregado): void;
}
