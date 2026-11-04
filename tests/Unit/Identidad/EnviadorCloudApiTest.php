<?php

declare(strict_types=1);

use Identidad\Infrastructure\Whatsapp\EnviadorCloudApi;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use Maestros\Domain\Contactos\Celular;
use Tests\Dobles\LoggerEspia;

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

/**
 * Una plantilla de autenticación con botón de copiar lleva el código dos
 * veces: en el cuerpo y en el botón. Meta cambia el tipo del botón a URL al
 * crearla, por eso `sub_type` es `url` aunque en la App se vea «Copiar código».
 * Sin el botón, Meta rechaza el envío.
 */
it('manda el codigo tambien en el boton de copiar', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['messages' => [['id' => 'wamid.1']]], 200)]);

    (new EnviadorCloudApi($http, 'https://graph.facebook.com/v26.0', '1234567890', 'token-secreto', 'desafio_ingreso', 'es'))
        ->enviar(Celular::desdeLocalBoliviano('70741828'), '4821');

    $http->assertSent(function (Request $peticion): bool {
        $componentes = $peticion->data()['template']['components'];

        return $componentes === [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '4821']]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '4821']]],
        ];
    });
});

it('no lanza si WhatsApp responde con error, pero lo registra', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['error' => ['message' => 'rate limit']], 429)]);

    $enviador = new EnviadorCloudApi($http, 'https://graph.facebook.com/v21.0', '1', 't', 'p', 'es');

    expect(fn () => $enviador->enviar(Celular::desdeLocalBoliviano('70741828'), '4821'))
        ->not->toThrow(Exception::class);
});

/**
 * Un rechazo de Meta tiene muchas causas —plantilla que no existe o no está
 * aprobada, parámetros que no coinciden, token vencido— y solo su error las
 * distingue. Sin él, el log dice «400» y hay que adivinar.
 */
it('registra el motivo que da Meta al rechazar', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['error' => [
        'message' => '(#132001) Template name does not exist in the translation',
        'type' => 'OAuthException',
        'code' => 132001,
        'error_data' => ['messaging_product' => 'whatsapp', 'details' => 'template name (desafio_ingreso) does not exist in es'],
        'fbtrace_id' => 'AbCdEf123',
    ]], 404)]);
    $log = new LoggerEspia;

    (new EnviadorCloudApi($http, 'https://graph.facebook.com/v26.0', '1', 't', 'desafio_ingreso', 'es', $log))
        ->enviar(Celular::desdeLocalBoliviano('70741828'), '4821');

    expect($log->registros)->toHaveCount(1)
        ->and($log->registros[0]['nivel'])->toBe('error')
        ->and($log->registros[0]['contexto'])->toBe([
            'status' => 404,
            'codigo' => 132001,
            'mensaje' => '(#132001) Template name does not exist in the translation',
            'detalle' => 'template name (desafio_ingreso) does not exist in es',
            'fbtrace_id' => 'AbCdEf123',
        ]);
});

it('el codigo no llega al log aunque Meta lo repita en su error', function () {
    $http = new Http;
    $http->fake(['*' => $http->response(['error' => [
        'message' => 'Invalid parameter',
        'code' => 100,
        'error_data' => ['details' => 'Parameter value 4821 is not valid'],
    ]], 400)]);
    $log = new LoggerEspia;

    (new EnviadorCloudApi($http, 'https://graph.facebook.com/v26.0', '1', 't', 'p', 'es', $log))
        ->enviar(Celular::desdeLocalBoliviano('70741828'), '4821');

    expect($log->registros)->toHaveCount(1)
        ->and(json_encode($log->registros[0], JSON_THROW_ON_ERROR))->not->toContain('4821')
        ->and($log->registros[0]['contexto']['detalle'])->toBe('Parameter value **** is not valid');
});

it('el codigo no llega al log si la conexion falla', function () {
    $http = new Http;
    $http->fake(fn () => throw new RuntimeException('cURL error 28 al mandar 4821'));
    $log = new LoggerEspia;

    (new EnviadorCloudApi($http, 'https://graph.facebook.com/v26.0', '1', 't', 'p', 'es', $log))
        ->enviar(Celular::desdeLocalBoliviano('70741828'), '4821');

    expect($log->registros)->toHaveCount(1)
        ->and(json_encode($log->registros[0], JSON_THROW_ON_ERROR))->not->toContain('4821');
});
