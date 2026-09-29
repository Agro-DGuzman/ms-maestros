<?php

declare(strict_types=1);

use App\Persistence\LotesDeScript;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * En SQL Server la estructura es la de database/sql/maestros-propiedades.sql,
 * sobre la que después se cargan los datos de la conciliación: ejecutarlo acá
 * deja un solo lugar donde vive. Es idempotente, así que da igual si alguien
 * lo corrió a mano antes.
 *
 * SQLite, que es solo para las pruebas, no entiende T-SQL: recibe la misma
 * forma armada con Blueprint, sin los CHECK del script. Si se cambia uno, se
 * cambia el otro.
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

        Schema::create('maestros_propiedad', function (Blueprint $tabla): void {
            $tabla->string('id_propiedad', 50)->primary();
            $tabla->string('nombre', 200);
            $tabla->string('codigo_de_socio', 15)->index();
            $tabla->boolean('activa')->default(true);
            $tabla->dateTime('creado_en')->useCurrent();
            $tabla->dateTime('actualizado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(DB::getDriverName() === 'sqlsrv' ? 'maestros.propiedad' : 'maestros_propiedad');
    }
};
