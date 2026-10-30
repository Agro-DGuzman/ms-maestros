<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Maestros\Application\Eventos\SaludDeLaBase;
use Psr\Log\LoggerInterface;
use Tests\Dobles\LoggerEspia;

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

it('cuando no responde deja en el log el motivo que dio la base', function () {
    // Sin el motivo, el consumidor repite «La base no responde» cada minuto y
    // no hay forma de distinguir un login rechazado de una base sin migrar.
    Schema::drop('maestros_evento_procesado');
    $log = new LoggerEspia;
    $this->app->instance(LoggerInterface::class, $log);

    app(SaludDeLaBase::class)->responde();

    expect($log->registros)->toHaveCount(1)
        ->and($log->registros[0]['nivel'])->toBe('warning')
        ->and($log->registros[0]['mensaje'])->toBe('La base no responde')
        ->and((string) $log->registros[0]['contexto']['error'])->toContain('maestros_evento_procesado');
});
