<?php

declare(strict_types=1);

namespace App\Persistence;

use RuntimeException;

/**
 * Parte un script T-SQL en los lotes que `DB::unprepared()` puede mandar.
 *
 * `GO` no es T-SQL: es el separador de lotes de sqlcmd y de SSMS. Hay que
 * partir el script ahí, porque algunas sentencias tienen que ir solas. Lo usan
 * las migraciones que ejecutan los scripts de `database/sql/`, que son a la
 * vez con lo que se cargan los datos: la estructura vive en un solo lugar.
 */
final class LotesDeScript
{
    /** @return list<string> */
    public static function de(string $archivo): array
    {
        $ruta = database_path("sql/{$archivo}");
        $script = is_readable($ruta) ? file_get_contents($ruta) : false;

        if ($script === false) {
            throw new RuntimeException("No se pudo leer database/sql/{$archivo}");
        }

        return self::desdeTexto($script);
    }

    /** @return list<string> */
    public static function desdeTexto(string $script): array
    {
        $lotes = preg_split('/^\s*GO\s*$/mi', $script) ?: [];

        return array_values(array_filter(
            array_map('trim', $lotes),
            static fn (string $lote): bool => $lote !== '' && ! self::soloComentarios($lote),
        ));
    }

    /** Un lote de solo comentarios es, para SQL Server, un lote vacío que falla. */
    private static function soloComentarios(string $lote): bool
    {
        $sinBloques = preg_replace('#/\*.*?\*/#s', '', $lote) ?? $lote;
        $sinLineas = preg_replace('/^\s*--.*$/m', '', $sinBloques) ?? $sinBloques;

        return trim($sinLineas) === '';
    }
}
