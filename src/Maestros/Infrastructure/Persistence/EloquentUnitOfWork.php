<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\NotificationPublisher;
use Core\Contracts\UnitOfWork;
use Core\Domain\AggregateRoot;
use Illuminate\Database\ConnectionInterface;

final class EloquentUnitOfWork implements UnitOfWork
{
    public function __construct(
        private readonly ConnectionInterface $conexion,
        private readonly NotificationPublisher $publicador,
    ) {}

    public function commit(AggregateRoot ...$agregados): void
    {
        $this->conexion->transaction(static function (): void {
            // Los repositorios ya escribieron dentro de esta transacción.
        });

        // Recién después de confirmar: despachar antes es cómo se emiten
        // eventos de cosas que después no pasaron.
        foreach ($agregados as $agregado) {
            foreach ($agregado->domainEvents() as $evento) {
                $this->publicador->publish($evento);
            }

            $agregado->clearDomainEvents();
        }
    }
}
