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
            DB::statement("IF SCHEMA_ID('identidad') IS NULL EXEC('CREATE SCHEMA identidad')");
        }

        Schema::create($this->tabla('desafios'), function (Blueprint $tabla): void {
            $tabla->string('id_de_desafio', 40)->primary();
            $tabla->string('celular', 20)->index();
            $tabla->string('digitos', 4);
            $tabla->dateTimeTz('expira_en');
            $tabla->unsignedTinyInteger('intentos_fallidos')->default(0);
            $tabla->boolean('consumido')->default(false);
            $tabla->dateTimeTz('emitido_el')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('desafios'));
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "identidad.{$nombre}" : "identidad_{$nombre}";
    }
};
