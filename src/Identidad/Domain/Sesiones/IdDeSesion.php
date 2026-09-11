<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Contracts\EntityId;
use Core\Results\DomainException;
use Core\Results\Error;
use Ramsey\Uuid\Uuid;

final readonly class IdDeSesion implements EntityId
{
    private function __construct(private string $id) {}

    public static function nueva(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function desde(string $id): self
    {
        $limpio = trim($id);

        if ($limpio === '') {
            throw new DomainException(
                Error::validation('SESION_INVALIDA', 'El identificador de sesión no puede estar vacío'),
            );
        }

        return new self($limpio);
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
