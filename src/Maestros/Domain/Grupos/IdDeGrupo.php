<?php

declare(strict_types=1);

namespace Maestros\Domain\Grupos;

use Core\Contracts\EntityId;
use Core\Results\DomainException;
use Core\Results\Error;

/** Viene de `OCRG`. Maestros lo replica y no lo edita (ADR 0003). */
final readonly class IdDeGrupo implements EntityId
{
    private function __construct(private string $id) {}

    public static function desde(string $id): self
    {
        $limpio = trim($id);

        if ($limpio === '') {
            throw new DomainException(
                Error::validation('GRUPO_INVALIDO', 'El identificador de grupo no puede estar vacío'),
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
