<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EmisorFalso;
use Tests\Dobles\EnviadorQueRecuerda;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->emisor = new EmisorFalso;
    $this->enviador = new EnviadorQueRecuerda;
    $this->app->instance(EmisorDeToken::class, $this->emisor);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(ReplicaDeEjemplo::MONICA), 'la-contrasena');
});

function pedirDesafio(): string
{
    return (string) test()->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.otpId');
}

it('entrega token, sesion y contexto con el codigo correcto', function () {
    $id = pedirDesafio();

    $respuesta = $this->postJson('/v1/auth/login', [
        'otpId' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
        'dispositivo' => ['instalacionId' => '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee', 'plataforma' => 'android'],
    ])->assertStatus(200);

    expect($respuesta->json('success'))->toBeTrue()
        ->and($respuesta->json('data.tokens.accessToken'))->toBe('jwt-de-prueba')
        ->and($respuesta->json('data.tokens.refreshToken'))->toBe('refresh-de-prueba')
        ->and($respuesta->json('data.dispositivoRegistrado'))->toBeTrue()
        ->and($respuesta->json('data.contexto.nombre'))->toBe('Grupo Monasterio')
        ->and($respuesta->json('data.contexto.grupoEconomico.socios'))->toHaveCount(1)
        ->and($this->emisor->pedidos)->toBe([ReplicaDeEjemplo::MONICA]);
});

it('registra la sesion con su dispositivo', function () {
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', [
        'otpId' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
        'dispositivo' => ['instalacionId' => '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee', 'plataforma' => 'ios'],
    ]);

    $abiertas = app(SesionRepository::class)->abiertasDe(IdDePersona::desde(ReplicaDeEjemplo::MONICA));

    expect($abiertas)->toHaveCount(1)
        ->and($abiertas[0]->dispositivo()->instalacion()->value())->toBe('3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee');
});

it('rechaza el codigo equivocado con 401 y no pide token', function () {
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => '0000'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);

    expect($this->emisor->pedidos)->toBe([]);
});

it('no deja usar el mismo desafio dos veces', function () {
    $id = pedirDesafio();
    $codigo = $this->enviador->ultimoCodigo;

    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => $codigo])->assertStatus(200);
    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => $codigo])->assertStatus(401);
});

it('un desafio inexistente responde el mismo CODIGO_INVALIDO', function () {
    $this->postJson('/v1/auth/login', ['otpId' => '9d4f0c1e-2b7a-4c3d-8e5f-6a7b8c9d0e1f', 'codigo' => '1234'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);
});

it('si Keycloak no responde devuelve 500 y no abre sesion', function () {
    $id = pedirDesafio();
    $this->emisor->caido = true;

    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => $this->enviador->ultimoCodigo])
        ->assertStatus(500)
        ->assertJsonPath('error.type', 'PROBLEM');

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBe([]);
});

/** El código del desafío leído de la base: a un número que no entra no se le envía nada. */
function codigoGuardado(string $otpId): string
{
    $desafio = app(DesafioRepository::class)->find(IdDeDesafio::desde($otpId));
    assert($desafio !== null);

    return $desafio->digitos();
}

it('entra con el numero habilitado y la sesion es del numero', function () {
    $this->postJson('/v1/auth/login', ['otpId' => pedirDesafio(), 'codigo' => $this->enviador->ultimoCodigo])->assertStatus(200);

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toHaveCount(1);
});

it('un numero sin credencial responde codigo invalido', function () {
    app(BovedaDeContrasenas::class)->olvidar(IdDePersona::desde(ReplicaDeEjemplo::MONICA));
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => codigoGuardado($id)])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);

    expect($this->emisor->pedidos)->toBe([]);
});

it('un numero en dos grupos responde codigo invalido aunque tenga credencial', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', ['otpId' => $id, 'codigo' => codigoGuardado($id)])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);

    expect($this->emisor->pedidos)->toBe([])
        ->and(app(SesionRepository::class)->abiertasDe(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBe([]);
});
