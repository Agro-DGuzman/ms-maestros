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
        Schema::create($this->tabla('sesiones'), function (Blueprint $tabla): void {
            $tabla->string('id_de_sesion', 40)->primary();
            $tabla->string('id_de_persona', 40)->index();
            $tabla->string('id_de_instalacion', 80)->nullable();
            $tabla->string('plataforma', 20)->nullable();
            $tabla->string('refresh_token_hash', 128)->nullable()->index();
            $tabla->dateTimeTz('iniciada_en');
            $tabla->dateTimeTz('expira_en');
            $tabla->dateTimeTz('cerrada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('sesiones'));
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "identidad.{$nombre}" : "identidad_{$nombre}";
    }
};
