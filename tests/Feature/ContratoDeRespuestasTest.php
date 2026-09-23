<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EmisorFalso;
use Tests\Dobles\EnviadorQueRecuerda;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\Contrato;

/*
 * Cada respuesta que la App puede recibir, contra el esquema que el contrato
 * declara para su status. ContratoTest mira que las rutas existan; esto mira
 * que lo que devuelven sea lo que la App espera leer.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->enviador = new EnviadorQueRecuerda;
    $this->app->instance(EmisorDeToken::class, new EmisorFalso);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde('p-8f2b1c40'), 'la-contrasena');
});

const CON_TOKEN = ['Authorization' => 'Bearer token-bueno'];

function iniciarSesion(): TestResponse
{
    $otpId = (string) test()->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.otpId');

    return test()->postJson('/v1/auth/login', [
        'otpId' => $otpId,
        'codigo' => test()->enviador->ultimoCodigo,
        'dispositivo' => ['instalacionId' => '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee', 'plataforma' => 'android'],
    ]);
}

it('POST /auth/otp', function (array $cuerpo, int $status) {
    $respuesta = $this->postJson('/v1/auth/otp', $cuerpo)->assertStatus($status);

    expect(Contrato::diferencias($respuesta, 'POST', '/auth/otp'))->toBe([]);
})->with([
    'desafio abierto' => [['telefono' => '70741828'], 200],
    'sin celular' => [[], 400],
    'celular que no es movil boliviano' => [['telefono' => '123'], 400],
]);

it('POST /auth/otp al superar el limite', function () {
    config(['identidad.desafios_por_hora' => 1]);
    $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);

    $respuesta = $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(429);

    expect($respuesta->json('error.code'))->toBe(['LIMITE_TASA_SUPERADO'])
        ->and(Contrato::diferencias($respuesta, 'POST', '/auth/otp'))->toBe([]);
});

it('POST /auth/login con el codigo correcto', function () {
    $respuesta = iniciarSesion()->assertStatus(200);

    expect(Contrato::diferencias($respuesta, 'POST', '/auth/login'))->toBe([]);
});

it('POST /auth/login con el codigo equivocado', function () {
    $otpId = (string) $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.otpId');
    $equivocado = $this->enviador->ultimoCodigo === '0000' ? '1111' : '0000';

    $respuesta = $this->postJson('/v1/auth/login', ['otpId' => $otpId, 'codigo' => $equivocado])
        ->assertStatus(401);

    expect($respuesta->json('error.code'))->toBe(['CODIGO_INVALIDO'])
        ->and(Contrato::diferencias($respuesta, 'POST', '/auth/login'))->toBe([]);
});

it('POST /auth/login con un codigo mal formado', function () {
    $respuesta = $this->postJson('/v1/auth/login', ['otpId' => 'e2b5a4d1-0c67-4f3a-9b18-5d2c7e91f306', 'codigo' => '12'])
        ->assertStatus(400);

    expect($respuesta->json('error.structuredMessage.0.campo'))->toBe('codigo')
        ->and(Contrato::diferencias($respuesta, 'POST', '/auth/login'))->toBe([]);
});

it('POST /auth/refresh', function () {
    $refresh = (string) iniciarSesion()->json('data.tokens.refreshToken');

    $renovado = $this->postJson('/v1/auth/refresh', ['refreshToken' => $refresh])->assertStatus(200);
    $invalido = $this->postJson('/v1/auth/refresh', ['refreshToken' => 'inventado'])->assertStatus(401);

    expect(Contrato::diferencias($renovado, 'POST', '/auth/refresh'))->toBe([])
        ->and($invalido->json('error.code'))->toBe(['REFRESH_TOKEN_INVALIDO'])
        ->and(Contrato::diferencias($invalido, 'POST', '/auth/refresh'))->toBe([]);
});

it('POST /auth/logout', function () {
    $refresh = (string) iniciarSesion()->json('data.tokens.refreshToken');

    $cerrada = $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh], CON_TOKEN)->assertStatus(200);
    $sinToken = $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh])->assertStatus(401);

    expect(Contrato::diferencias($cerrada, 'POST', '/auth/logout'))->toBe([])
        ->and(Contrato::diferencias($sinToken, 'POST', '/auth/logout'))->toBe([]);
});

it('las rutas autenticadas', function (string $ruta) {
    $conToken = $this->getJson('/v1'.$ruta, CON_TOKEN)->assertStatus(200);
    $sinToken = $this->getJson('/v1'.$ruta)->assertStatus(401);

    expect(Contrato::diferencias($conToken, 'GET', $ruta))->toBe([])
        ->and($sinToken->json('error.code'))->toBe(['TOKEN_INVALIDO'])
        ->and(Contrato::diferencias($sinToken, 'GET', $ruta))->toBe([]);
})->with(['/mi-cuenta', '/socios']);

it('el validador detecta una respuesta que no cumple', function () {
    // Sin esto, un validador que no validara nada dejaría todo en verde.
    $respuesta = TestResponse::fromBaseResponse(
        new JsonResponse(['data' => ['idDeDesafio' => 'x'], 'success' => true, 'error' => null]),
    );

    expect(Contrato::diferencias($respuesta, 'POST', '/auth/otp'))->not->toBe([]);
});
