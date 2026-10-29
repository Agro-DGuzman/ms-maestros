<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Ingesta\ReplicaDePropiedades;
use Maestros\Domain\Propiedades\PropiedadReplicada;

/**
 * `activa` y `creado_en` no se nombran: la primera queda sin leer hasta que
 * se borre (EM9), y la segunda la pone la base al crear la fila.
 */
final class EloquentReplicaDePropiedades implements ReplicaDePropiedades
{
    public function guardarSiEsMasNueva(PropiedadReplicada $propiedad): bool
    {
        $tabla = DB::getDriverName() === 'sqlsrv' ? 'maestros.propiedad' : 'maestros_propiedad';
        $fila = DB::table($tabla)->where('id_propiedad', $propiedad->id())->first(['vigente_desde']);
        $guardada = $fila === null ? null : self::vigencia(((array) $fila)['vigente_desde'] ?? null);

        // Sin vigencia guardada es una de la semilla: cualquier foto la pisa.
        if ($fila !== null && $guardada !== null && $propiedad->vigenteDesde() <= $guardada) {
            return false;
        }

        $valores = [
            'nombre' => $propiedad->nombre(),
            'codigo_de_socio' => $propiedad->socio()->value(),
            'estado' => $propiedad->estado()->value,
            // Con microsegundos y sin desfase: la columna es datetime2(6) en UTC.
            'vigente_desde' => $propiedad->vigenteDesde()->format('Y-m-d H:i:s.u'),
        ];

        if ($fila === null) {
            DB::table($tabla)->insert(['id_propiedad' => $propiedad->id()] + $valores);
        } else {
            DB::table($tabla)->where('id_propiedad', $propiedad->id())->update($valores + [
                'actualizado_en' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * SQL Server la devuelve con seis decimales y sin desfase; se lee como
     * UTC explícito para no depender de la zona del proceso.
     */
    private static function vigencia(mixed $valor): ?DateTimeImmutable
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $recortado = preg_replace('/(\.\d{6})\d+/', '$1', trim($valor)) ?? $valor;

        return new DateTimeImmutable($recortado, new DateTimeZone('UTC'));
    }
}
