<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * En SQL Server la estructura es la de database/sql/maestros-configuracion.sql,
 * con sus CHECK: los valores se cargan a mano y las reglas tienen que frenar
 * el error de tipeo en el INSERT. Ejecutarlo acá deja un solo lugar donde vive.
 *
 * SQLite, que es solo para las pruebas, no entiende T-SQL: recibe la misma
 * forma armada con Blueprint, con las claves únicas pero sin los CHECK. Si se
 * cambia uno, se cambia el otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            foreach (LotesDeScript::de('maestros-configuracion.sql') as $lote) {
                DB::unprepared($lote);
            }

            return;
        }

        Schema::create('maestros_cuentas_bancarias', function (Blueprint $tabla): void {
            $tabla->increments('id');
            $tabla->string('banco', 100);
            $tabla->string('numero_cuenta', 50);
            $tabla->integer('orden')->unique();
            $tabla->unique(['banco', 'numero_cuenta']);
        });

        Schema::create('maestros_configuracion_app', function (Blueprint $tabla): void {
            $tabla->string('clave', 60)->primary();
            $tabla->string('valor', 500);
        });
    }

    public function down(): void
    {
        $sqlsrv = DB::getDriverName() === 'sqlsrv';

        Schema::dropIfExists($sqlsrv ? 'maestros.configuracion_app' : 'maestros_configuracion_app');
        Schema::dropIfExists($sqlsrv ? 'maestros.cuentas_bancarias' : 'maestros_cuentas_bancarias');
    }
};
