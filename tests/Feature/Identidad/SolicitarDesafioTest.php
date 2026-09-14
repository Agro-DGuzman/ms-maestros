<?php

declare(strict_types=1);

use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Identidad\Infrastructure\Whatsapp\EnviarDesafioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;
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

it('encola el envio para cualquier numero, registrado o no', function () {
    Queue::fake();

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);
    $this->postJson('/v1/auth/otp', ['telefono' => '79999999'])->assertStatus(200);

    Queue::assertPushed(EnviarDesafioJob::class, 2);
});

it('persiste el desafio para cualquier numero, registrado o no', function () {
    Queue::fake();

    $conocido = $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);
    $desconocido = $this->postJson('/v1/auth/otp', ['telefono' => '79999999']);

    $repo = app(DesafioRepository::class);

    expect($repo->find(IdDeDesafio::desde((string) $conocido->json('data.idDeDesafio'))))->not->toBeNull()
        ->and($repo->find(IdDeDesafio::desde((string) $desconocido->json('data.idDeDesafio'))))->not->toBeNull();
});

it('el handler no consulta el directorio', function () {
    Queue::fake();

    $directorio = new class implements DirectorioDeContactos
    {
        public int $consultas = 0;

        public function buscarPorCelular(Celular $celular): ?IdDePersona
        {
            $this->consultas++;

            return null;
        }

        public function contexto(IdDePersona $id): ?ContextoDeContacto
        {
            return null;
        }
    };

    $this->app->instance(DirectorioDeContactos::class, $directorio);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    expect($directorio->consultas)->toBe(0);
});

it('el desafio encolado sigue llevando el celular que lo pidio', function () {
    $respuesta = $this->postJson('/v1/auth/otp', ['telefono' => '70741828']);

    $desafio = app(DesafioRepository::class)->find(
        IdDeDesafio::desde((string) $respuesta->json('data.idDeDesafio')),
    );

    expect($desafio)->not->toBeNull()
        ->and($desafio->celular()->e164())->toBe('+59170741828')
        ->and($this->enviador->enviados)->toHaveCount(1)
        ->and($this->enviador->enviados[0]['digitos'])->toMatch('/^\d{4}$/');
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
