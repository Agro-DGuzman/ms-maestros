<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Maestros\Application\Eventos\SaludDeLaBase;

final class SaludFija implements SaludDeLaBase
{
    public function __construct(private readonly bool $responde) {}

    public function responde(): bool
    {
        return $this->responde;
    }
}
