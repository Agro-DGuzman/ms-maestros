<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->emisor = new EmisorFalso();          // definido en IniciarSesionTest.php
    $this->enviador = new EnviadorQueRecuerda(); // idem
    $this->app->instance(EmisorDeToken::class, $this->emisor);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde('p-8f2b1c40'), 'la-contrasena');

    $id = (string) $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.idDeDesafio');
    $this->login = $this->postJson('/v1/auth/login', [
        'idDeDesafio' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
    ]);
});

it('renueva con un refresh token conocido', function () {
    $respuesta = $this->postJson('/v1/auth/refresh', [
        'refreshToken' => $this->login->json('data.refreshToken'),
    ])->assertStatus(200);

    expect($respuesta->json('data.token'))->toBe('jwt-2')
        ->and($respuesta->json('data.refreshToken'))->toBe('refresh-2');
});

it('no renueva con un refresh token desconocido', function () {
    $this->postJson('/v1/auth/refresh', ['refreshToken' => 'inventado'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['NO_AUTENTICADO']);
});

it('cierra la sesion y deja de renovar', function () {
    $refresh = (string) $this->login->json('data.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh])->assertStatus(200);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toBe([]);

    $this->postJson('/v1/auth/refresh', ['refreshToken' => $refresh])->assertStatus(401);
});

it('cerrar una sesion ya cerrada responde 200 igual', function () {
    $refresh = (string) $this->login->json('data.refreshToken');

    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh])->assertStatus(200);
    $this->postJson('/v1/auth/logout', ['refreshToken' => $refresh])->assertStatus(200);
});
