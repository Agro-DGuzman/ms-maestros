<?php

declare(strict_types=1);

namespace Core\Contracts;

use Core\Domain\DomainEvent;

interface NotificationPublisher
{
    public function publish(DomainEvent $evento): void;
}
