<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\PropiedadesDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
    PropiedadesDeEjemplo::sembrar();
});

const CON_TOKEN_DE_MONICA = ['Authorization' => 'Bearer token-bueno'];

it('lista las propiedades activas del socio, ordenadas por nombre', function () {
    $respuesta = $this->getJson('/v1/socios/C-004871/propiedades', CON_TOKEN_DE_MONICA)->assertStatus(200);

    // PROP-003 es del mismo socio pero está dada de baja.
    expect($respuesta->json('data.items'))->toBe([
        ['id' => 'PROP-007', 'nombre' => 'Lote 07 · Cuatro Cañadas'],
        ['id' => 'PROP-014', 'nombre' => 'Lote 14 · San Julián'],
    ])->and($respuesta->json('error'))->toBeNull();
});

it('un socio del grupo sin propiedades responde una lista vacia', function () {
    $this->getJson('/v1/socios/C-004873/propiedades', CON_TOKEN_DE_MONICA)
        ->assertStatus(200)
        ->assertExactJson(['data' => ['items' => []], 'success' => true, 'error' => null]);
});

it('un socio de otro grupo responde 403', function () {
    $this->getJson('/v1/socios/C-005210/propiedades', CON_TOKEN_DE_MONICA)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO'])
        ->assertJsonPath('data', null);
});

it('un cardCode inexistente responde 403, no 404', function () {
    // El alcance se verifica antes que la existencia: un 404 diría qué
    // códigos existen.
    $this->getJson('/v1/socios/C-999999/propiedades', CON_TOKEN_DE_MONICA)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);
});

it('un cardCode de mas de 15 caracteres responde 403, no 400', function () {
    $this->getJson('/v1/socios/'.str_repeat('C', 16).'/propiedades', CON_TOKEN_DE_MONICA)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);
});

it('sin token responde 401', function () {
    $this->getJson('/v1/socios/C-004871/propiedades')
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['TOKEN_INVALIDO']);
});
