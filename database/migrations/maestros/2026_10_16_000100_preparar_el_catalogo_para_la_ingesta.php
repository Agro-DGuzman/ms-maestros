<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las cuatro columnas con las que el catálogo pasa a ser réplica en parte:
 * de cuándo es la ficha, si está dado de baja y de dónde vino. Todo es
 * aditivo y anulable, así que la base nueva sirve al código que corre hoy y
 * la migración se puede correr antes del despliegue.
 *
 * En SQL Server vuelve a correr el script del catálogo, que es idempotente y
 * el único lugar donde vive esa estructura.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            foreach (LotesDeScript::de('maestros-productos.sql') as $lote) {
                DB::unprepared($lote);
            }

            return;
        }

        Schema::table('maestros_producto', function (Blueprint $tabla): void {
            $tabla->dateTimeTz('vigente_desde')->nullable();
            $tabla->dateTimeTz('dado_de_baja_el')->nullable();
            $tabla->string('origen_esquema', 20)->nullable();
            $tabla->unsignedBigInteger('origen_evento_id')->nullable();
        });
    }

    public function down(): void
    {
        $columnas = ['vigente_desde', 'dado_de_baja_el', 'origen_esquema', 'origen_evento_id'];

        if (DB::getDriverName() === 'sqlsrv') {
            foreach ($columnas as $columna) {
                DB::statement("ALTER TABLE maestros.producto DROP COLUMN {$columna}");
            }

            return;
        }

        Schema::table('maestros_producto', function (Blueprint $tabla) use ($columnas): void {
            $tabla->dropColumn($columnas);
        });
    }
};
