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
            DB::statement("IF SCHEMA_ID('maestros') IS NULL EXEC('CREATE SCHEMA maestros')");
        }

        Schema::create($this->tabla('grupos'), function (Blueprint $tabla): void {
            $tabla->string('id_de_grupo', 40)->primary();
            $tabla->string('nombre', 200);
            $tabla->dateTimeTz('vigente_desde');
            $tabla->dateTimeTz('importado_el');
        });

        Schema::create($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->string('codigo_de_socio', 15)->primary();
            $tabla->string('razon_social', 200);
            $tabla->string('id_de_grupo', 40)->index();
            $tabla->dateTimeTz('vigente_desde');
            $tabla->dateTimeTz('importado_el');
        });

        Schema::create($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->string('id_de_persona', 40)->primary();
            $tabla->string('codigo_de_socio', 15)->index();
            $tabla->string('nombre', 200);
            $tabla->string('celular', 20)->unique();
            $tabla->dateTimeTz('habilitada_el')->nullable();
            $tabla->dateTimeTz('vigente_desde');
            $tabla->dateTimeTz('importado_el');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('contactos'));
        Schema::dropIfExists($this->tabla('socios'));
        Schema::dropIfExists($this->tabla('grupos'));
    }

    /** En SQL Server hay esquema; en SQLite de pruebas se aplana el nombre. */
    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
};
