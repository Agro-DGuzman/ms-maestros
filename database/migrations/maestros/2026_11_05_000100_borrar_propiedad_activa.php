<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Se borra `activa`: quedó sin leer ni escribir desde que entró `estado`
 * (EM9), y en Azure decía 1 en propiedades inactivas. Ninguna revisión
 * desplegada la nombra, así que la migración puede ir antes o después del
 * push.
 *
 * En SQL Server vuelve a correr el script de propiedades, que es idempotente
 * y el único lugar donde vive esa estructura; el borrado es su último bloque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            foreach (LotesDeScript::de('maestros-propiedades.sql') as $lote) {
                DB::unprepared($lote);
            }

            return;
        }

        Schema::table('maestros_propiedad', function (Blueprint $tabla): void {
            $tabla->dropColumn('activa');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE maestros.propiedad ADD activa BIT NOT NULL CONSTRAINT DF_propiedad_activa DEFAULT (1)');
            DB::statement("UPDATE maestros.propiedad SET activa = CASE WHEN estado = N'activo' THEN 1 ELSE 0 END");

            return;
        }

        Schema::table('maestros_propiedad', function (Blueprint $tabla): void {
            $tabla->boolean('activa')->default(true);
        });
    }
};
