<?php

declare(strict_types=1);

namespace App\Persistence;

use Illuminate\Support\Facades\DB;

/**
 * Comparación de texto que ignora mayúsculas **y acentos**.
 *
 * La colación por defecto de Azure SQL es `SQL_Latin1_General_CP1_CI_AS`: CI
 * ignora la caja, AS **distingue** los acentos. Con eso, un operador que
 * teclea «Chavez» no encuentra a «Chávez», ni «Ortuno» a «Ortuño», y la
 * pantalla no le dice que el problema es la tilde. Pedir la colación
 * `Latin1_General_CI_AI` en la comparación lo resuelve, y alcanza con
 * aplicarla a la columna.
 *
 * En SQLite el resultado es peor a propósito: sin ICU ese motor no tiene
 * colaciones acento-insensibles, así que queda `lower()`, que resuelve la caja
 * y nada más. Por eso el comportamiento real solo se puede comprobar contra
 * SQL Server, no en la batería que corre sobre SQLite.
 */
trait ComparacionSinAcentos
{
    /**
     * El nombre de columna tiene que ser un literal del código, nunca algo que
     * venga de la petición: lo que sale de acá termina en el SQL sin ligar.
     *
     * @param  literal-string  $columna
     * @return literal-string
     */
    protected function comoTextoInsensible(string $columna): string
    {
        return DB::getDriverName() === 'sqlsrv'
            ? "{$columna} COLLATE Latin1_General_CI_AI"
            : "lower({$columna})";
    }
}
