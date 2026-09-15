<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement("IF SCHEMA_ID('backoffice') IS NULL EXEC('CREATE SCHEMA backoffice')");
        }

        Schema::create($this->tabla('asientos_de_bitacora'), function (Blueprint $tabla): void {
            $tabla->string('id_de_asiento', 40)->primary();
            $tabla->string('id_de_operador', 100);
            $tabla->string('operador', 200);
            $tabla->string('id_de_persona', 50);
            $tabla->string('accion', 20);
            $tabla->dateTimeTz('ocurrio_el');
            $tabla->string('direccion_ip', 45);

            // El historial siempre se pide por persona y ordenado por fecha.
            $tabla->index(['id_de_persona', 'ocurrio_el'], 'ix_bitacora_persona');
        });

        // Sin timestamps: `ocurrio_el` es el momento del hecho y no hay otro.
        // Sin `updated_at` porque no hay update.
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('asientos_de_bitacora'));
    }

    /** En SQL Server hay esquema; en el SQLite de pruebas se aplana el nombre. */
    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "backoffice.{$nombre}" : "backoffice_{$nombre}";
    }
};
