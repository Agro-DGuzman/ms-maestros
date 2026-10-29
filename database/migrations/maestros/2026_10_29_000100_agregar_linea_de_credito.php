<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La línea de crédito, con el texto que mandó SAP y no como DECIMAL: este
 * servicio solo la reenvía, y pdo_sqlsrv devuelve un decimal menor que 1 sin
 * el cero adelante («.00»), que ms-comercial rechaza. Hay más socios en cero
 * que con línea.
 *
 * Aditiva y anulable, pero tiene que estar antes del despliegue: el
 * repositorio la escribe en cada `save`, así que sin ella toda ingesta de
 * socios responde 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->string('linea_de_credito', 22)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->dropColumn('linea_de_credito');
        });
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
};
