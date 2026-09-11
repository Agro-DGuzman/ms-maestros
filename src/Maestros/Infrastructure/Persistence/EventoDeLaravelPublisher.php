<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\NotificationPublisher;
use Core\Domain\DomainEvent;
use Illuminate\Contracts\Events\Dispatcher;

final class EventoDeLaravelPublisher implements NotificationPublisher
{
    public function __construct(private readonly Dispatcher $eventos) {}

    public function publish(DomainEvent $evento): void
    {
        $this->eventos->dispatch($evento->eventName(), [$evento]);
    }
}
