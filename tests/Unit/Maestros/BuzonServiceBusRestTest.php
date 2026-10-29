<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use Maestros\Application\Eventos\MensajeRecibido;
use Maestros\Infrastructure\ServiceBus\BuzonServiceBusRest;

const UBICACION_DEL_BLOQUEO = 'https://agp-dev.servicebus.windows.net/comercial-eventos/subscriptions/sub-ms-maestros/messages/1/lt';

function buzonRest(Http $http): BuzonServiceBusRest
{
    return new BuzonServiceBusRest($http, 'agp-dev', 'comercial-eventos', 'sub-ms-maestros', 'escucha', 'Y2xhdmUtZGUtcHJ1ZWJh');
}

it('lee el mensaje bloqueado', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('{"eventoId":"x"}', 201, [
        'BrokerProperties' => '{"MessageId":"m-1","DeliveryCount":2,"LockToken":"lt"}',
        'Location' => UBICACION_DEL_BLOQUEO,
    ])]);

    $mensaje = buzonRest($http)->recibir();

    expect($mensaje)->toBeInstanceOf(MensajeRecibido::class)
        ->and($mensaje?->cuerpo)->toBe('{"eventoId":"x"}')
        ->and($mensaje?->idDeMensaje)->toBe('m-1')
        ->and($mensaje?->numeroDeEntrega)->toBe(2)
        ->and($mensaje?->referenciaDeBloqueo)->toBe(UBICACION_DEL_BLOQUEO);

    $http->assertSent(static fn (Request $pedido): bool => $pedido->method() === 'POST'
        && $pedido->url() === 'https://agp-dev.servicebus.windows.net/comercial-eventos/subscriptions/sub-ms-maestros/messages/head?timeout=10'
        && str_starts_with($pedido->header('Authorization')[0] ?? '', 'SharedAccessSignature sr=https%3a%2f%2fagp-dev'));
});

it('con una espera pedida, el bus espera eso y no la configurada', function () {
    // Con una tanda llena del outbox, el consumidor vuelve pronto (EM6).
    $http = new Http;
    $http->fake(['*' => $http->response('', 204)]);

    buzonRest($http)->recibir(1);

    $http->assertSent(static fn (Request $pedido): bool => str_ends_with($pedido->url(), '/messages/head?timeout=1'));
});

it('sin mensajes devuelve null', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('', 204)]);

    expect(buzonRest($http)->recibir())->toBeNull();
});

it('un error del bus es una excepcion, no un buzon vacio', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('boom', 500)]);

    buzonRest($http)->recibir();
})->throws(RuntimeException::class);

it('completar borra el mensaje bloqueado', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('', 200)]);

    buzonRest($http)->completar(new MensajeRecibido('{}', 'm-1', 1, UBICACION_DEL_BLOQUEO));

    $http->assertSent(static fn (Request $pedido): bool => $pedido->method() === 'DELETE'
        && $pedido->url() === UBICACION_DEL_BLOQUEO);
});

it('si no se pudo completar lo dice', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('', 410)]);

    buzonRest($http)->completar(new MensajeRecibido('{}', 'm-1', 1, UBICACION_DEL_BLOQUEO));
})->throws(RuntimeException::class);
