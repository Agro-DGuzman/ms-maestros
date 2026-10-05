<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La primera respuesta a cada envío del Sincronizador, para devolver la misma
 * si el envío se repite. La clave es la terna: el Sincronizador reintenta un
 * POST que dio 409 como PUT con la misma Idempotency-Key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->tabla(), function (Blueprint $tabla): void {
            $tabla->char('clave', 32);
            $tabla->string('metodo', 6);
            $tabla->string('ruta', 100);
            $tabla->unsignedSmallInteger('status');
            $tabla->longText('cuerpo')->nullable();
            $tabla->dateTimeTz('recibida_el');

            $tabla->primary(['clave', 'metodo', 'ruta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->tabla());
    }

    private function tabla(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'maestros.ingesta_respuestas' : 'maestros_ingesta_respuestas';
    }
};
