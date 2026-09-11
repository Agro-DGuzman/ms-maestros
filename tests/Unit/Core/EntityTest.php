<?php

declare(strict_types=1);

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use Core\Domain\DomainEvent;

final class IdDePrueba implements EntityId
{
    public function __construct(private readonly string $valor) {}

    public function value(): string
    {
        return $this->valor;
    }

    public function equals(EntityId $otro): bool
    {
        return $otro instanceof self && $otro->valor === $this->valor;
    }
}

final class EventoDePrueba extends DomainEvent
{
    public function __construct(private readonly string $agregado)
    {
        parent::__construct();
    }

    public function aggregateType(): string
    {
        return 'Prueba';
    }

    public function aggregateId(): string
    {
        return $this->agregado;
    }

    public function eventName(): string
    {
        return 'prueba.ocurrio';
    }

    public function payload(): array
    {
        return ['agregado' => $this->agregado];
    }
}

final class AgregadoDePrueba extends AggregateRoot
{
    public function __construct(EntityId $id)
    {
        parent::__construct($id);
    }

    public function hacerAlgo(): void
    {
        $this->addDomainEvent(new EventoDePrueba($this->id()->value()));
    }
}

it('rechaza un identificador vacio', function () {
    expect(fn () => new AgregadoDePrueba(new IdDePrueba('   ')))
        ->toThrow(InvalidArgumentException::class);
});

it('acumula y limpia eventos de dominio', function () {
    $agregado = new AgregadoDePrueba(new IdDePrueba('A-1'));

    expect($agregado->domainEvents())->toBe([]);

    $agregado->hacerAlgo();

    expect($agregado->domainEvents())->toHaveCount(1)
        ->and($agregado->domainEvents()[0]->aggregateId())->toBe('A-1');

    $agregado->clearDomainEvents();

    expect($agregado->domainEvents())->toBe([]);
});

it('la lista devuelta no deja modificar la interna', function () {
    $agregado = new AgregadoDePrueba(new IdDePrueba('A-1'));
    $agregado->hacerAlgo();

    $copia = $agregado->domainEvents();
    $copia[] = new EventoDePrueba('A-2');

    expect($agregado->domainEvents())->toHaveCount(1);
});

it('el evento trae identificador, marca de tiempo y tipo', function () {
    $evento = new EventoDePrueba('A-1');

    expect($evento->eventId)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($evento->eventType())->toBe('EventoDePrueba')
        ->and($evento->eventName())->toBe('prueba.ocurrio')
        ->and($evento->occurredOn)->toBeInstanceOf(DateTimeImmutable::class);
});

it('dos identidades con el mismo valor son iguales', function () {
    expect((new IdDePrueba('A-1'))->equals(new IdDePrueba('A-1')))->toBeTrue()
        ->and((new IdDePrueba('A-1'))->equals(new IdDePrueba('A-2')))->toBeFalse();
});
