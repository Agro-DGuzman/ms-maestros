<?php

declare(strict_types=1);

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\EnviadorEspia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
});

it('responde igual para un numero registrado y uno que no lo esta', function () {
    $registrado = $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);
    $desconocido = $this->postJson('/v1/auth/otp', ['telefono' => '79999999']);

    expect($registrado->status())->toBe(200)
        ->and($desconocido->status())->toBe(200)
        ->and(array_keys($registrado->json()))->toBe(array_keys($desconocido->json()))
        ->and($registrado->json('success'))->toBeTrue()
        ->and($desconocido->json('success'))->toBeTrue()
        ->and($registrado->json('error'))->toBeNull()
        ->and($desconocido->json('error'))->toBeNull()
        ->and($registrado->json('data.idDeDesafio'))->toBeString()
        ->and($desconocido->json('data.idDeDesafio'))->toBeString();
});

it('manda el WhatsApp solo si el numero esta registrado', function () {
    $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);

    expect($this->enviador->enviados)->toHaveCount(1)
        ->and($this->enviador->enviados[0]['celular'])->toBe('+59170741828')
        ->and($this->enviador->enviados[0]['digitos'])->toMatch('/^\d{4}$/');

    $this->postJson('/v1/auth/otp', ['telefono' => '79999999']);

    expect($this->enviador->enviados)->toHaveCount(1);
});

it('guarda el desafio solo cuando hay a quien mandarlo', function () {
    $respuesta = $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);

    $desafio = app(DesafioRepository::class)->find(
        IdDeDesafio::desde((string) $respuesta->json('data.idDeDesafio')),
    );

    expect($desafio)->not->toBeNull()
        ->and($desafio->celular()->e164())->toBe('+59170741828');
});

it('rechaza un telefono que no es movil boliviano con 422', function () {
    $this->postJson('/v1/auth/otp', ['telefono' => '123'])
        ->assertStatus(422)
        ->assertJsonPath('error.type', 'VALIDATION');
});

it('corta con 429 al superar el limite por celular', function () {
    config(['identidad.desafios_por_hora' => 2]);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);
    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])
        ->assertStatus(429)
        ->assertJsonPath('error.code', ['LIMITE_DE_TASA']);
});
