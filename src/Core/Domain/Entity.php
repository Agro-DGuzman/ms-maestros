<?php

declare(strict_types=1);

namespace Core\Domain;

use Core\Contracts\EntityId;
use InvalidArgumentException;

abstract class Entity
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    protected function __construct(protected readonly EntityId $id)
    {
        if (trim($id->value()) === '') {
            throw new InvalidArgumentException('El identificador no puede estar vacío');
        }
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    /** @return list<DomainEvent> */
    public function domainEvents(): array
    {
        return $this->domainEvents;
    }

    public function addDomainEvent(DomainEvent $evento): void
    {
        $this->domainEvents[] = $evento;
    }

    public function clearDomainEvents(): void
    {
        $this->domainEvents = [];
    }
}
