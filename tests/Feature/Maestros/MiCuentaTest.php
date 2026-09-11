<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

final class VerificadorFalso implements VerificadorDeToken
{
    public function __construct(private readonly ?string $persona) {}

    public function verificar(string $jwt): ?IdDePersona
    {
        return $jwt === 'token-bueno' && $this->persona !== null
            ? IdDePersona::desde($this->persona)
            : null;
    }
}

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
});

it('devuelve el contexto de la persona del token', function () {
    $respuesta = $this->getJson('/v1/mi-cuenta', ['Authorization' => 'Bearer token-bueno'])
        ->assertStatus(200);

    expect($respuesta->json('data.nombre'))->toBe('Monica Salvatierra')
        ->and($respuesta->json('data.iniciales'))->toBe('MS')
        ->and($respuesta->json('data.celular'))->toBe('+59170741828')
        ->and($respuesta->json('data.grupoEconomico.id'))->toBe('GRP-014')
        ->and($respuesta->json('data.grupoEconomico.socios'))->toHaveCount(3)
        ->and($respuesta->json('error'))->toBeNull();
});

it('responde 401 sin cabecera Authorization', function () {
    $this->getJson('/v1/mi-cuenta')
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['NO_AUTENTICADO'])
        ->assertJsonPath('data', null);
});

it('responde 401 con un token que no verifica', function () {
    $this->getJson('/v1/mi-cuenta', ['Authorization' => 'Bearer token-falso'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['NO_AUTENTICADO']);
});

it('responde 404 si el token nombra a alguien que no existe', function () {
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-fantasma'));

    $this->getJson('/v1/mi-cuenta', ['Authorization' => 'Bearer token-bueno'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['CONTACTO_NO_ENCONTRADO']);
});
