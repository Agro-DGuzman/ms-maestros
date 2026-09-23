<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\VerificadorDeToken;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EmisorFalso;
use Tests\Dobles\EnviadorQueRecuerda;
use Tests\Dobles\VerificadorFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->emisor = new EmisorFalso;
    $this->enviador = new EnviadorQueRecuerda;
    $this->app->instance(EmisorDeToken::class, $this->emisor);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde('p-8f2b1c40'), 'la-contrasena');

    $id = (string) $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.otpId');
    $this->login = $this->postJson('/v1/auth/login', [
        'otpId' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
        'dispositivo' => ['instalacionId' => '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee', 'plataforma' => 'android'],
    ]);
});

it('renueva con un refresh token conocido', function () {
    $respuesta = $this->postJson('/v1/auth/refresh', [
        'refreshToken' => $this->login->json('data.tokens.refreshToken'),
    ])->assertStatus(200);

    expect($respuesta->json('data.accessToken'))->toBe('jwt-2')
        ->and($respuesta->json('data.tokenType'))->toBe('Bearer')
        ->and($respuesta->json('data.refreshToken'))->toBe('refresh-2');
});

it('no renueva con un refresh token desconocido', function () {
    $this->postJson('/v1/auth/refresh', ['refreshToken' => 'inventado'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['REFRESH_TOKEN_INVALIDO']);
});

const INSTALACION_DEL_LOGIN = '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee';
const CABECERAS_DE_LOGOUT = ['Authorization' => 'Bearer token-bueno'];

it('cierra la sesion y deja de renovar', function () {
    $refresh = (string) $this->login->json('data.tokens.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh], CABECERAS_DE_LOGOUT)
        ->assertStatus(200)
        ->assertJsonPath('data.sesionCerrada', true);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toBe([]);

    $this->postJson('/v1/auth/refresh', ['refreshToken' => $refresh])->assertStatus(401);
});

it('cerrar una sesion ya cerrada responde 200 igual, y dice que no habia nada', function () {
    $refresh = (string) $this->login->json('data.tokens.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh], CABECERAS_DE_LOGOUT)->assertStatus(200);
    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh], CABECERAS_DE_LOGOUT)
        ->assertStatus(200)
        ->assertJsonPath('data.sesionCerrada', false);
});

it('sin token no cierra nada', function () {
    $refresh = (string) $this->login->json('data.tokens.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['TOKEN_INVALIDO']);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toHaveCount(1);
});

it('cierra la sesion por su instalacion, sin el refresh token', function () {
    // El cuerpo es opcional en el contrato: la App puede haber perdido el
    // refresh token y aun así querer dar de baja el dispositivo.
    $this->postJson('/v1/auth/logout', ['instalacionId' => INSTALACION_DEL_LOGIN], CABECERAS_DE_LOGOUT)
        ->assertStatus(200)
        ->assertJsonPath('data.sesionCerrada', true);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toBe([]);
});

it('con el token de una persona no cierra la sesion de otra', function () {
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-otra-persona'));
    $refresh = (string) $this->login->json('data.tokens.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh, 'instalacionId' => INSTALACION_DEL_LOGIN], CABECERAS_DE_LOGOUT)
        ->assertStatus(200)
        ->assertJsonPath('data.sesionCerrada', false);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toHaveCount(1);
});
