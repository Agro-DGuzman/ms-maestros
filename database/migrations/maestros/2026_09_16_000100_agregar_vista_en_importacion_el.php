<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `importado_el` no sirve como señal de «la vi en la última corrida»: solo se
 * escribe cuando la fila efectivamente se guarda, y la importación omite las
 * que no son más nuevas. Esta columna se estampa siempre, venga o no con
 * cambios, que es lo único que la vuelve comparable entre corridas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->dateTimeTz('vista_en_importacion_el')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->dropColumn('vista_en_importacion_el');
        });
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
};
