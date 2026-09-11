<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Contracts\EntityId;
use Core\Results\DomainException;
use Ramsey\Uuid\Uuid;

/** Identificador propio: es lo único que cruza de Identidad a Maestros. */
final readonly class IdDePersona implements EntityId
{
    private function __construct(private string $id) {}

    public static function desde(string $id): self
    {
        $limpio = trim($id);

        if ($limpio === '') {
            throw new DomainException(ContactoErrors::idInvalido());
        }

        return new self($limpio);
    }

    public static function nueva(): self
    {
        return new self('p-'.substr(Uuid::uuid4()->toString(), 0, 8));
    }

    public function value(): string
    {
        return $this->id;
    }

    public function equals(EntityId $otro): bool
    {
        return $otro instanceof self && $otro->id === $this->id;
    }
}
