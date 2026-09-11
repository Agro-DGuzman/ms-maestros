<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

use Core\Contracts\EntityId;
use Core\Results\DomainException;
use Core\Results\Error;

final readonly class IdDeInstalacion implements EntityId
{
    private function __construct(private string $id) {}

    public static function desde(string $id): self
    {
        $limpio = trim($id);

        if ($limpio === '') {
            throw new DomainException(
                Error::validation('INSTALACION_INVALIDA', 'El identificador de instalación no puede estar vacío'),
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
