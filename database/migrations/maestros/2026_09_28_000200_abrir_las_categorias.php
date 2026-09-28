<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * La primera versión del catálogo limitaba las categorías a las cuatro que
 * enumera el contrato. Esas son ejemplos: el catálogo real de la web tiene
 * fungicidas, biológicos y coadyuvantes. El script ya no crea el CHECK; esto
 * lo quita de las bases que lo recibieron.
 *
 * SQLite no lo tuvo nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlsrv') {
            return;
        }

        DB::unprepared(
            "IF OBJECT_ID('maestros.CK_categoria_codigo', 'C') IS NOT NULL "
            .'ALTER TABLE maestros.categoria DROP CONSTRAINT CK_categoria_codigo;',
        );
    }

    /** Volver a limitarlas fallaría con las categorías que ya estén cargadas. */
    public function down(): void {}
};
