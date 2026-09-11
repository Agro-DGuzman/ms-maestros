<?php

declare(strict_types=1);

namespace Core\Domain;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use ReflectionClass;

/**
 * Evento de dominio: lo que el agregado emite dentro de la transacción.
 * No confundir con el evento de integración (`sap.socio.actualizado`), que es
 * forma de transporte y vive en Infrastructure.
 */
abstract class DomainEvent
{
    public readonly string $eventId;

    public readonly DateTimeImmutable $occurredOn;

    protected function __construct()
    {
        $this->eventId = Uuid::uuid4()->toString();
        $this->occurredOn = new DateTimeImmutable;
    }

    abstract public function aggregateType(): string;

    abstract public function aggregateId(): string;

    abstract public function eventName(): string;

    /** @return array<string, mixed> */
    abstract public function payload(): array;

    public function eventType(): string
    {
        return (new ReflectionClass($this))->getShortName();
    }
}
