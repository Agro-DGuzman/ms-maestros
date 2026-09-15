<?php

declare(strict_types=1);

namespace Tests\Soporte;

use DateTimeImmutable;
use Identidad\Application\Contracts\RelojDelSistema;

final class RelojFijo implements RelojDelSistema
{
    public function __construct(private readonly string $momento = '2026-09-15T14:30:00+00:00') {}

    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->momento);
    }
}
