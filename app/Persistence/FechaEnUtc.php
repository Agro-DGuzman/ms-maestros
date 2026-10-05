<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Las columnas de fecha de la réplica guardan UTC sin desfase. Formatear una
 * fecha que trae su propio desfase guardaría la hora local, y al releerla como
 * UTC la vigencia se correría horas: la réplica retrocedería o se congelaría.
 */
trait FechaEnUtc
{
    private static function enUtc(?DateTimeImmutable $fecha): ?string
    {
        return $fecha?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
