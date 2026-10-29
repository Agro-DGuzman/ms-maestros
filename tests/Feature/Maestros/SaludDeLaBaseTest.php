<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Maestros\Application\Eventos\SaludDeLaBase;

uses(RefreshDatabase::class);

it('responde con la base migrada', function () {
    expect(app(SaludDeLaBase::class)->responde())->toBeTrue();
});

it('una base sin migrar cuenta como caida', function () {
    // Si solo se comprobara la conexión, el consumidor recibiría cada mensaje,
    // reventaría al escribirlo y a los cinco intentos lo mandaría a la cola
    // de mensajes muertos.
    Schema::drop('maestros_evento_procesado');

    expect(app(SaludDeLaBase::class)->responde())->toBeFalse();
});
