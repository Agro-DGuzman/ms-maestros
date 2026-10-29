<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Los instantes con microsegundos de los eventos de dominio. `FechaEnUtc`
 * guarda al segundo, que es la regla de la réplica de SAP; acá dos fotos del
 * mismo segundo tienen que distinguirse, y los casts de Eloquent en SQL
 * Server formatean sin fracción. Copiado de ms-comercial.
 */
final class InstanteUtc
{
    public static function paraGuardar(DateTimeImmutable $instante): string
    {
        return $instante->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }

    /**
     * SQL Server devuelve un `datetimeoffset` con siete decimales, y PHP solo
     * entiende seis: el séptimo (cientos de nanosegundos) se descarta.
     */
    public static function leido(string $valor): DateTimeImmutable
    {
        $recortado = preg_replace('/(\.\d{6})\d+/', '$1', trim($valor)) ?? $valor;

        return (new DateTimeImmutable($recortado))->setTimezone(new DateTimeZone('UTC'));
    }
}
