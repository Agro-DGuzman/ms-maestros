<?php

declare(strict_types=1);

namespace Core\Contracts;

/**
 * Identidad tipada. Reemplaza al UUID del core Java: acá las réplicas de SAP
 * se identifican con su clave natural (CardCode, ItemCode) y solo lo que nace
 * en este servicio usa un identificador propio.
 */
interface EntityId
{
    public function value(): string;

    public function equals(self $otro): bool;
}
