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

        Schema::create($this->tabla('cambios_de_catalogo'), function (Blueprint $tabla): void {
            $tabla->string('id_de_cambio', 40)->primary();
            $tabla->string('id_de_operador', 100);
            $tabla->string('operador', 200);
            // id_producto y no el código: un producto puede editarse antes de
            // tener código. El código se copia para poder leer el historial.
            $tabla->integer('id_producto');
            $tabla->string('item_code', 50)->nullable();
            $tabla->string('campo', 40);
            $tabla->string('valor_anterior', 1000)->nullable();
            $tabla->string('valor_nuevo', 1000)->nullable();
            $tabla->dateTimeTz('ocurrio_el');
            $tabla->string('direccion_ip', 45);

            // El historial siempre se pide por producto y ordenado por fecha.
            $tabla->index(['id_producto', 'ocurrio_el'], 'ix_cambios_de_catalogo_producto');
        });

        // Sin timestamps ni update: un cambio asentado no se modifica.
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('cambios_de_catalogo'));
    }

    /** En SQL Server hay esquema; en el SQLite de pruebas se aplana el nombre. */
    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "backoffice.{$nombre}" : "backoffice_{$nombre}";
    }
};
