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
        Schema::create($this->tabla('credenciales'), function (Blueprint $tabla): void {
            $tabla->string('id_de_persona', 40)->primary();
            $tabla->text('contrasena_cifrada');
            $tabla->dateTimeTz('rotada_el');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('credenciales'));
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "identidad.{$nombre}" : "identidad_{$nombre}";
    }
};
