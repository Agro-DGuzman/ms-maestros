<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Las propiedades que ningún evento de ms-comercial tocó: las de la semilla
 * (EM11). Se corre a mano y después de que comercial republique las suyas
 * (paso 2 del 12.3 de su guía): antes, borraría propiedades que todavía
 * pueden llegar. El borrado es físico porque no hay un evento que borre una
 * propiedad, y una que quedara sería de un socio inventado para siempre.
 */
final class PropiedadesSinEventoCommand extends Command
{
    protected $signature = 'maestros:propiedades-sin-evento {--borrar : Borrarlas, después de mirar la lista}';

    protected $description = 'Lista las propiedades que nunca llegaron por un evento, y con la opción las borra';

    public function handle(): int
    {
        $tabla = DB::getDriverName() === 'sqlsrv' ? 'maestros.propiedad' : 'maestros_propiedad';
        $sinEvento = DB::table($tabla)->whereNull('vigente_desde')->orderBy('id_propiedad')
            ->get(['id_propiedad', 'nombre', 'codigo_de_socio']);

        if ($sinEvento->isEmpty()) {
            $this->info('Todas las propiedades llegaron por un evento.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Nombre', 'Socio'],
            $sinEvento->map(static fn (object $fila): array => array_values((array) $fila))->all(),
        );

        if (! $this->option('borrar')) {
            return self::SUCCESS;
        }

        $borradas = DB::table($tabla)->whereNull('vigente_desde')->delete();
        $this->info("Se borraron {$borradas} propiedades.");

        return self::SUCCESS;
    }
}
