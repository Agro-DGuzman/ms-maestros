<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Domain\AggregateRoot;

interface UnitOfWork
{
    /**
     * Abre la transacción, persiste, confirma, y recién entonces despacha
     * los eventos de dominio de los agregados recibidos. Despachar antes es
     * cómo se emiten eventos de cosas que después no pasaron.
     */
    public function commit(AggregateRoot ...$agregados): void;
}
