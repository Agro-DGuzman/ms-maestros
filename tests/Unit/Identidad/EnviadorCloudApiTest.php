<?php

declare(strict_types=1);

use Identidad\Infrastructure\Whatsapp\EnviadorCloudApi;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use Maestros\Domain\Contactos\Celular;

it('manda la plantilla con el codigo como parametro', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['messages' => [['id' => 'wamid.1']]], 200)]);

    (new EnviadorCloudApi($http, 'https://graph.facebook.com/v21.0', '1234567890', 'token-secreto', 'desafio_ingreso', 'es'))
        ->enviar(Celular::desdeLocalBoliviano('70741828'), '4821');

    $http->assertSent(function (Request $peticion): bool {
        $cuerpo = $peticion->data();

        return str_contains($peticion->url(), '/1234567890/messages')
            && $cuerpo['to'] === '59170741828'
            && $cuerpo['type'] === 'template'
            && $cuerpo['template']['name'] === 'desafio_ingreso'
            && $cuerpo['template']['components'][0]['parameters'][0]['text'] === '4821';
    });
});

it('no lanza si WhatsApp responde con error, pero lo registra', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['error' => ['message' => 'rate limit']], 429)]);

    $enviador = new EnviadorCloudApi($http, 'https://graph.facebook.com/v21.0', '1', 't', 'p', 'es');

    expect(fn () => $enviador->enviar(Celular::desdeLocalBoliviano('70741828'), '4821'))
        ->not->toThrow(Exception::class);
});
