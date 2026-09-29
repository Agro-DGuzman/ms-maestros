<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * Las mismas cinco filas que `database/sql/maestros-propiedades-ejemplo.sql`.
 * Los tres primeros socios son del grupo de la persona `p-8f2b1c40` de la
 * semilla; `C-004873` queda sin propiedades a propósito, y `C-005210` es de
 * otro grupo, para ver que nada ajeno se cuela.
 */
final class PropiedadesDeEjemplo
{
    public static function sembrar(): void
    {
        DB::table(self::tabla())->insert([
            ['id_propiedad' => 'PROP-014', 'nombre' => 'Lote 14 · San Julián', 'codigo_de_socio' => 'C-004871', 'activa' => true],
            ['id_propiedad' => 'PROP-007', 'nombre' => 'Lote 07 · Cuatro Cañadas', 'codigo_de_socio' => 'C-004871', 'activa' => true],
            ['id_propiedad' => 'PROP-003', 'nombre' => 'Lote 03 · Pailón', 'codigo_de_socio' => 'C-004871', 'activa' => false],
            ['id_propiedad' => 'PROP-021', 'nombre' => 'Lote 21 · Okinawa', 'codigo_de_socio' => 'C-004872', 'activa' => true],
            ['id_propiedad' => 'PROP-031', 'nombre' => 'Lote 31 · Montero', 'codigo_de_socio' => 'C-005210', 'activa' => true],
        ]);
    }

    public static function tabla(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'maestros.propiedad' : 'maestros_propiedad';
    }
}
