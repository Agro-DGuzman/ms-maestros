<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use Maestros\Infrastructure\ServiceBus\PublicadorServiceBusRest;

function publicadorRest(Http $http): PublicadorServiceBusRest
{
    return new PublicadorServiceBusRest($http, 'ns', 'maestros-eventos', 'ms-maestros-publica', 'Y2xhdmUtZGUtcHJ1ZWJh');
}

/** @return array<string, mixed> */
function sobreParaPublicar(): array
{
    return [
        'eventoId' => '4f7c2a1e-9b3d-4e8a-a1c2-7d5e6f8a9b10',
        'tipo' => 'maestros.socio.actualizado',
        'datos' => ['cardCode' => 'C-005210', 'razonSocial' => 'Vargas Peña SRL'],
    ];
}

it('publica el sobre con MessageId, la cabecera tipo y el texto sin escapes', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('', 201)]);

    publicadorRest($http)->publicar(sobreParaPublicar());

    $http->assertSent(static function (Request $pedido): bool {
        $propiedades = json_decode($pedido->header('BrokerProperties')[0] ?? '', true);

        return $pedido->method() === 'POST'
            && $pedido->url() === 'https://ns.servicebus.windows.net/maestros-eventos/messages'
            && is_array($propiedades)
            && $propiedades['MessageId'] === '4f7c2a1e-9b3d-4e8a-a1c2-7d5e6f8a9b10'
            // Las propiedades propias viajan con el valor en JSON: entre comillas.
            && ($pedido->header('tipo')[0] ?? null) === '"maestros.socio.actualizado"'
            && str_contains($pedido->body(), 'Vargas Peña SRL');
    });
});

it('una respuesta que no es 201 es una excepcion', function () {
    $http = new Http;
    $http->fake(['*' => $http->response('', 401)]);

    publicadorRest($http)->publicar(sobreParaPublicar());
})->throws(RuntimeException::class, '401');
