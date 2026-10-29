<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * El bus de eventos de dominio (EM1, EM7): el outbox de lo que ms-maestros
 * publica y el registro de lo que ya procesó. Van juntos para que en Azure sea
 * una sola migración, antes del push.
 *
 * El outbox lleva un índice común y no uno filtrado: con uno filtrado, SQL
 * Server rechaza la escritura de un cliente con QUOTED_IDENTIFIER apagado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tabla('evento_saliente'), function (Blueprint $tabla): void {
            $tabla->uuid('evento_id')->primary();
            $tabla->string('tipo', 60);
            $tabla->text('sobre');
            $tabla->dateTimeTz('creado_en', 6);
            $tabla->dateTimeTz('publicado_en', 6)->nullable();
            $tabla->integer('intentos')->default(0);
            $tabla->index(['publicado_en', 'creado_en'], 'ix_evento_saliente_pendientes');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            DB::statement('ALTER TABLE maestros.evento_saliente ADD CONSTRAINT CK_evento_saliente_sobre CHECK (ISJSON(sobre) = 1)');
        }

        // «¿Ya vi este mensaje?». `resultado` responde «¿por qué no se ve el
        // cambio?» sin ir a buscar el mensaje al bus.
        Schema::create($this->tabla('evento_procesado'), function (Blueprint $tabla): void {
            $tabla->string('evento_id', 36)->primary();
            $tabla->string('tipo', 60);
            $tabla->string('resultado', 30);
            $tabla->dateTimeTz('procesado_en', 6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla('evento_procesado'));
        Schema::dropIfExists($this->tabla('evento_saliente'));
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
};
