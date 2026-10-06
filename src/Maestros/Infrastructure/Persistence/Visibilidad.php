<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * La única definición en SQL de «lo que la App ve» de un contacto: activo, sin
 * baja, y de un socio activo y sin baja. La usan el repositorio (alcance,
 * contexto, ingreso, renovación) y la pantalla de Contactos (el conflicto de
 * celular): si divergieran, el back-office marcaría un conflicto que el
 * ingreso no ve, o al revés.
 */
final class Visibilidad
{
    /** `$contacto` y `$socio` son los alias de las dos tablas en la consulta. */
    public static function exigir(Builder $consulta, string $contacto, string $socio): void
    {
        $consulta->where("{$contacto}.activo", true)
            ->whereNull("{$contacto}.dado_de_baja_el")
            ->where("{$socio}.activo", true)
            ->whereNull("{$socio}.dado_de_baja_el");
    }
}
