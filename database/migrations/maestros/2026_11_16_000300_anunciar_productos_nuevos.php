<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * `anunciado_el` marca el producto que ya se anunció como nuevo (NP11). Todo
 * lo que existe al migrar se marca acá, visible o no (NP12): si no, el primer
 * envío de SAP anunciaría los productos de la carga inicial, y uno inactivo
 * que se reactive, o uno que reciba su categoría, se anunciaría sin ser nuevo.
 *
 * La marca va solo en esta migración y nunca en el script, que se vuelve a
 * correr entero cada vez que se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            foreach (LotesDeScript::de('maestros-productos.sql') as $lote) {
                DB::unprepared($lote);
            }

            DB::statement('UPDATE maestros.producto SET anunciado_el = SYSUTCDATETIME() WHERE anunciado_el IS NULL');

            return;
        }

        Schema::table('maestros_producto', function (Blueprint $tabla): void {
            $tabla->dateTimeTz('anunciado_el')->nullable();
        });

        DB::table('maestros_producto')->whereNull('anunciado_el')->update(['anunciado_el' => now('UTC')->format('Y-m-d H:i:s')]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE maestros.producto DROP COLUMN anunciado_el');

            return;
        }

        Schema::table('maestros_producto', function (Blueprint $tabla): void {
            $tabla->dropColumn('anunciado_el');
        });
    }
};
