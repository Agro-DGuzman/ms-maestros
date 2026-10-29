<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las propiedades pasan a llegar por los eventos de ms-comercial: `estado`
 * en lugar de `activa`, y `vigente_desde` para ordenar las fotos (EM8, EM9).
 * Todo es aditivo, así que la revisión vieja, que todavía lee `activa`,
 * sigue andando con la base nueva y la migración va antes del push. `activa`
 * se borra después, junto con las otras columnas en desuso.
 *
 * En SQL Server vuelve a correr el script de propiedades, que es idempotente
 * y el único lugar donde vive esa estructura. SQLite recibe la misma forma
 * con Blueprint, sin los CHECK.
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
            $tabla->string('estado', 10)->default('activo');
            $tabla->dateTime('vigente_desde', 6)->nullable();
            $tabla->index(['codigo_de_socio', 'estado'], 'ix_propiedad_socio_estado');
        });

        DB::table('maestros_propiedad')->where('activa', false)->whereNull('vigente_desde')->update(['estado' => 'inactivo']);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('DROP INDEX IF EXISTS IX_propiedad_socio_estado ON maestros.propiedad');
            DB::statement('ALTER TABLE maestros.propiedad DROP CONSTRAINT IF EXISTS CK_propiedad_estado');
            DB::statement('ALTER TABLE maestros.propiedad DROP CONSTRAINT IF EXISTS DF_propiedad_estado');
            DB::statement('ALTER TABLE maestros.propiedad DROP COLUMN IF EXISTS estado, vigente_desde');

            return;
        }

        Schema::table('maestros_propiedad', function (Blueprint $tabla): void {
            $tabla->dropIndex('ix_propiedad_socio_estado');
            $tabla->dropColumn(['estado', 'vigente_desde']);
        });
    }
};
