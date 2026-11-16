<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El token push de Firebase vive con la sesión (NP2): un push va solo a los
 * tokens de sesiones abiertas (NP6), y la sesión decide quién recibe. El largo
 * es el que acepta TokenPush; los de Firebase rondan los 160 caracteres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tabla('sesiones'), function (Blueprint $tabla): void {
            $tabla->string('token_push', 4096)->nullable();
            $tabla->dateTimeTz('token_push_actualizado_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tabla('sesiones'), function (Blueprint $tabla): void {
            $tabla->dropColumn(['token_push', 'token_push_actualizado_en']);
        });
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "identidad.{$nombre}" : "identidad_{$nombre}";
    }
};
