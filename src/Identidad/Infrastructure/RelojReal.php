<?php

declare(strict_types=1);

namespace Identidad\Infrastructure;

use DateTimeImmutable;
use Identidad\Application\Contracts\RelojDelSistema;

final class RelojReal implements RelojDelSistema
{
    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
