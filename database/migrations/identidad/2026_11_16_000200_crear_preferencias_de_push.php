<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La copia de las preferencias de push que publica ms-comercial (NP8): una
 * fila por persona. `vigente_desde` es el ocurridoEn del evento, con
 * microsegundos, para que una foto vieja no pise una nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tabla('preferencias_de_push'), function (Blueprint $tabla): void {
            $tabla->string('id_de_persona', 100)->primary();
            $tabla->boolean('facturas');
            $tabla->boolean('pagos');
            $tabla->boolean('reportes');
            $tabla->boolean('catalogo');
            $tabla->dateTimeTz('vigente_desde', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('preferencias_de_push'));
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "identidad.{$nombre}" : "identidad_{$nombre}";
    }
};
