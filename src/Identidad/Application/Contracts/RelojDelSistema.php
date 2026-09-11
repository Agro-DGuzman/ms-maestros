<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use DateTimeImmutable;

interface RelojDelSistema
{
    public function ahora(): DateTimeImmutable;
}
