<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\VerificadorDeToken;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EmisorFalso;
use Tests\Dobles\EnviadorQueRecuerda;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\ReplicaDeEjemplo;

/*
 * NP18: Firebase rota el token, y la App lo cambia sin volver a iniciar
 * sesión. La sesión se busca por la persona del token de acceso y por la
 * instalación; cualquier otra cosa es 404.
 */

uses(RefreshDatabase::class);

const ESTE_TELEFONO = '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee';
const OTRO_TELEFONO = '9a7e2c11-5b3d-4f60-8e14-2d6c0b9f7a35';
const CON_ACCESO_AL_TOKEN = ['Authorization' => 'Bearer token-bueno'];

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->enviador = new EnviadorQueRecuerda;
    $this->app->instance(EmisorDeToken::class, new EmisorFalso);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso(ReplicaDeEjemplo::MONICA));

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(ReplicaDeEjemplo::MONICA), 'la-contrasena');

    entrarDesde(ESTE_TELEFONO);
});

/** @param array<string, string> $extra */
function entrarDesde(string $instalacion, array $extra = []): void
{
    $otpId = (string) test()->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.otpId');

    test()->postJson('/v1/auth/login', [
        'otpId' => $otpId,
        'codigo' => test()->enviador->ultimoCodigo,
        'dispositivo' => ['instalacionId' => $instalacion, 'plataforma' => 'android'] + $extra,
    ])->assertStatus(200);
}

function sesionDe(string $instalacion): SesionDeAplicacion
{
    foreach (app(SesionRepository::class)->abiertasDe(IdDePersona::desde(ReplicaDeEjemplo::MONICA)) as $sesion) {
        if ($sesion->dispositivo()?->instalacion()->value() === $instalacion) {
            return $sesion;
        }
    }

    throw new RuntimeException("No hay una sesión abierta con {$instalacion}");
}

function cambiarToken(string $instalacion, mixed $token, array $cabeceras = CON_ACCESO_AL_TOKEN): TestResponse
{
    return test()->putJson("/v1/dispositivos/{$instalacion}/token", ['tokenPush' => $token], $cabeceras);
}

it('cambia el token de la sesion propia y responde dispositivoRegistrado true', function () {
    cambiarToken(ESTE_TELEFONO, 'fcm-token-nuevo')
        ->assertStatus(200)
        ->assertJsonPath('data.dispositivoRegistrado', true)
        ->assertJsonPath('success', true);

    expect(sesionDe(ESTE_TELEFONO)->tokenPush()?->valor())->toBe('fcm-token-nuevo')
        ->and(sesionDe(ESTE_TELEFONO)->tokenPushActualizadoEn())->not->toBeNull();
});

it('reemplaza un token que ya tenia', function () {
    cambiarToken(ESTE_TELEFONO, 'fcm-token-viejo')->assertStatus(200);

    cambiarToken(ESTE_TELEFONO, 'fcm-token-nuevo')->assertStatus(200);

    expect(sesionDe(ESTE_TELEFONO)->tokenPush()?->valor())->toBe('fcm-token-nuevo');
});

it('el mismo token en otra sesion abierta se le quita: un token es de la ultima sesion que lo registro', function () {
    entrarDesde(OTRO_TELEFONO, ['tokenPush' => 'fcm-token-repetido']);

    cambiarToken(ESTE_TELEFONO, 'fcm-token-repetido')->assertStatus(200);

    expect(sesionDe(ESTE_TELEFONO)->tokenPush()?->valor())->toBe('fcm-token-repetido')
        ->and(sesionDe(OTRO_TELEFONO)->tokenPush())->toBeNull();
});

it('la instalacion de otra persona responde 404', function () {
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('n-59172112233'));

    cambiarToken(ESTE_TELEFONO, 'fcm-token-nuevo')
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['DISPOSITIVO_NO_ENCONTRADO']);

    expect(sesionDe(ESTE_TELEFONO)->tokenPush())->toBeNull();
});

it('una instalacion sin sesion abierta responde 404', function () {
    cambiarToken(OTRO_TELEFONO, 'fcm-token-nuevo')
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['DISPOSITIVO_NO_ENCONTRADO']);
});

it('una sesion cerrada responde 404', function () {
    $this->postJson('/v1/auth/logout', ['instalacionId' => ESTE_TELEFONO], CON_ACCESO_AL_TOKEN)->assertStatus(200);

    cambiarToken(ESTE_TELEFONO, 'fcm-token-nuevo')
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['DISPOSITIVO_NO_ENCONTRADO']);
});

it('un token mal formado es 400 y no cambia nada', function (mixed $token, string $codigo) {
    cambiarToken(ESTE_TELEFONO, 'fcm-token-bueno')->assertStatus(200);

    cambiarToken(ESTE_TELEFONO, $token)
        ->assertStatus(400)
        ->assertJsonPath('error.code', [$codigo])
        ->assertJsonPath('error.structuredMessage.0.campo', 'tokenPush');

    expect(sesionDe(ESTE_TELEFONO)->tokenPush()?->valor())->toBe('fcm-token-bueno');
})->with([
    'con espacio' => ['con espacio', 'PARAMETRO_INVALIDO'],
    'vacio' => ['', 'CAMPO_REQUERIDO'],
    'ausente' => [null, 'CAMPO_REQUERIDO'],
    'de 4001 caracteres' => [str_repeat('a', 4001), 'PARAMETRO_INVALIDO'],
    'que no es texto' => [123, 'PARAMETRO_INVALIDO'],
]);

it('una instalacion que no es un uuid es 400', function () {
    cambiarToken('no-es-un-uuid', 'fcm-token-nuevo')
        ->assertStatus(400)
        ->assertJsonPath('error.structuredMessage.0.campo', 'instalacionId');
});

it('sin token de acceso es 401 y no cambia nada', function () {
    cambiarToken(ESTE_TELEFONO, 'fcm-token-nuevo', [])
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['TOKEN_INVALIDO']);

    expect(sesionDe(ESTE_TELEFONO)->tokenPush())->toBeNull();
});
