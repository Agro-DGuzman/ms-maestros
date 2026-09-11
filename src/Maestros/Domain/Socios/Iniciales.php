<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Stringable;

final readonly class Iniciales implements Stringable
{
    public function __construct(private string $letras) {}

    public function __toString(): string
    {
        return $this->letras;
    }
}
