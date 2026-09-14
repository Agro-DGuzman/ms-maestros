<?php

declare(strict_types=1);

namespace App\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * En SQL Server las tablas viven bajo un esquema (`maestros.socios`); en el
 * SQLite de las pruebas no hay esquemas, así que el nombre se aplana
 * (`maestros_socios`). Estaba copiado en los seis records.
 *
 * Vive en `app/` y no en un módulo porque lo comparten los records de
 * `Maestros` y los de `Identidad`, que no pueden referenciarse entre sí.
 * `app/` es el host: depender del framework es exactamente su trabajo.
 */
trait TablaConEsquema
{
    protected function tablaEn(string $esquema, string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv'
            ? "{$esquema}.{$nombre}"
            : "{$esquema}_{$nombre}";
    }
}
