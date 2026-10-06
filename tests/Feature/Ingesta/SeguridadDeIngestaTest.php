<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Soporte\TokenDeEntra;

uses(RefreshDatabase::class);

/*
 * Una ruta de prueba con los tres middlewares de la ingesta, en el orden real.
 * Las rutas de verdad entran en la tarea siguiente; acá se prueba la puerta.
 */
beforeEach(function () {
    TokenDeEntra::configurar();
    Route::post('/ingesta/v1/__prueba', fn () => new JsonResponse(['data' => ['cardCode' => 'X'], 'success' => true, 'error' => null], 201))
        ->middleware(['gateway:ingesta', 'ingesta.token', 'ingesta.idempotencia']);
});

function cabecerasDeIngesta(array $sobrescribir = []): array
{
    return array_merge([
        'X-Gateway-Secret' => 'secreto-del-gateway',
        'Authorization' => 'Bearer '.TokenDeEntra::valido(),
        'Idempotency-Key' => str_repeat('a', 32),
    ], $sobrescribir);
}

function pedirIngesta(array $cabeceras): TestResponse
{
    return test()->postJson('/ingesta/v1/__prueba', [], $cabeceras);
}

it('pasa con secreto, token e idempotencia validos', function () {
    pedirIngesta(cabecerasDeIngesta())->assertStatus(201);
});

it('sin el secreto del gateway, o con otro, responde 403 aunque el token sea valido', function (array $cabeceras) {
    pedirIngesta($cabeceras)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);
})->with([
    'sin cabecera' => [fn () => array_diff_key(cabecerasDeIngesta(), ['X-Gateway-Secret' => 1])],
    'otro secreto' => [fn () => cabecerasDeIngesta(['X-Gateway-Secret' => 'otro'])],
]);

it('con el secreto sin configurar rechaza todo, aunque la cabecera venga vacia', function () {
    // Falla cerrado: sin secreto, cualquiera que conozca la dirección del
    // Container App podría escribir socios.
    config(['ingesta.gateway.secreto' => '']);

    pedirIngesta(cabecerasDeIngesta(['X-Gateway-Secret' => '']))->assertStatus(403);
});

it('rechaza con 401 un token que no sirve, y dice en el log por que', function (Closure $cabeceras, string $motivo) {
    Log::spy();

    pedirIngesta($cabeceras())
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['TOKEN_INVALIDO']);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $mensaje, array $contexto = []): bool => ($contexto['motivo'] ?? null) === $motivo,
    );
})->with([
    'sin Authorization' => [fn () => array_diff_key(cabecerasDeIngesta(), ['Authorization' => 1]), 'formato'],
    // Firmado con la clave 2 pero diciendo ser la 1: la firma no verifica.
    'firmado con otra clave' => [fn () => cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(kid: 'clave-2', kidEnCabecera: 'clave-1')]), 'firma'],
    // Un token v1: el manifiesto de la API no quedó en versión 2.
    'emisor v1' => [fn () => cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(['iss' => 'https://sts.windows.net/tenant-de-prueba/'])]), 'iss'],
    'otra audiencia' => [fn () => cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(['aud' => 'api://otra'])]), 'aud'],
    'sin el rol' => [fn () => cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(['roles' => ['Ingesta.Comercial.Escribir']])]), 'rol'],
    'vencido hace 2 minutos' => [fn () => cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(['exp' => time() - 120])]), 'vencido'],
]);

it('tolera 60 segundos de diferencia de reloj', function () {
    pedirIngesta(cabecerasDeIngesta(['Authorization' => 'Bearer '.TokenDeEntra::valido(['exp' => time() - 30])]))
        ->assertStatus(201);
});

it('una clave nueva hace pedir de nuevo el JWKS, pero no mas de una vez cada 5 minutos', function () {
    // Entra rota claves: el JWKS cacheado no la tiene todavía.
    pedirIngesta(cabecerasDeIngesta())->assertStatus(201);

    TokenDeEntra::publicar(['clave-1', 'clave-2']);

    pedirIngesta(cabecerasDeIngesta([
        'Authorization' => 'Bearer '.TokenDeEntra::valido(kid: 'clave-2'),
        'Idempotency-Key' => str_repeat('b', 32),
    ]))->assertStatus(201);

    Http::assertSentCount(2);

    // Otra clave desconocida dentro de los 5 minutos: no se vuelve a pedir,
    // aunque Entra ya la publique.
    TokenDeEntra::publicar(['clave-1', 'clave-2', 'clave-3']);

    pedirIngesta(cabecerasDeIngesta([
        'Authorization' => 'Bearer '.TokenDeEntra::valido(kid: 'clave-3'),
        'Idempotency-Key' => str_repeat('c', 32),
    ]))->assertStatus(401);

    Http::assertSentCount(2);
});

it('si no puede obtener las claves de Entra responde 503', function () {
    TokenDeEntra::caido();

    pedirIngesta(cabecerasDeIngesta())
        ->assertStatus(503)
        ->assertJsonPath('error.code', ['ENTRA_NO_DISPONIBLE']);
});

it('sin el tenant configurado responde CONFIGURACION_INCOMPLETA', function () {
    config(['ingesta.entra.tenant' => '']);

    pedirIngesta(cabecerasDeIngesta())
        ->assertStatus(500)
        ->assertJsonPath('error.code', ['CONFIGURACION_INCOMPLETA']);
});
