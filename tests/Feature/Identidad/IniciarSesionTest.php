<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
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
        // Sin tokenPush no hay a dónde mandar: registrar es guardar el token (NP3).
        ->and($respuesta->json('data.dispositivoRegistrado'))->toBeFalse()
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

const LUCIA = 'n-59170223344';
const TELEFONO_COMPARTIDO = '3f1c9a20-8d44-4b6e-9d21-6a7f0c4b18ee';

/** @param array<string, string> $dispositivo */
function entrarCon(string $telefono, array $dispositivo): TestResponse
{
    $id = (string) test()->postJson('/v1/auth/otp', ['telefono' => $telefono])->json('data.otpId');

    return test()->postJson('/v1/auth/login', [
        'otpId' => $id,
        'codigo' => test()->enviador->ultimoCodigo,
        'dispositivo' => $dispositivo,
    ]);
}

/** @return list<SesionDeAplicacion> */
function abiertasDe(string $persona): array
{
    return app(SesionRepository::class)->abiertasDe(IdDePersona::desde($persona));
}

it('el login guarda el token push y responde dispositivoRegistrado true', function () {
    $respuesta = entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-token-de-prueba'])
        ->assertStatus(200);

    expect($respuesta->json('data.dispositivoRegistrado'))->toBeTrue()
        ->and(abiertasDe(ReplicaDeEjemplo::MONICA)[0]->tokenPush()?->valor())->toBe('fcm-token-de-prueba');
});

it('sin tokenPush entra y responde dispositivoRegistrado false', function () {
    $respuesta = entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android'])
        ->assertStatus(200);

    expect($respuesta->json('data.dispositivoRegistrado'))->toBeFalse()
        ->and(abiertasDe(ReplicaDeEjemplo::MONICA)[0]->dispositivo()?->instalacion()->value())->toBe(TELEFONO_COMPARTIDO);
});

it('un tokenPush mal formado es 400', function () {
    entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'con espacio'])
        ->assertStatus(400)
        ->assertJsonPath('error.structuredMessage.0.campo', 'dispositivo.tokenPush');

    expect(abiertasDe(ReplicaDeEjemplo::MONICA))->toBe([]);
});

it('otra persona que entra en el mismo telefono le quita el dispositivo a la sesion anterior', function () {
    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(LUCIA), 'otra-contrasena');
    entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-de-monica']);

    entrarCon('70223344', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-de-lucia'])
        ->assertStatus(200);

    expect(abiertasDe(ReplicaDeEjemplo::MONICA))->toHaveCount(1)
        ->and(abiertasDe(ReplicaDeEjemplo::MONICA)[0]->dispositivo())->toBeNull()
        ->and(abiertasDe(LUCIA)[0]->tokenPush()?->valor())->toBe('fcm-de-lucia');
});

it('la misma persona que vuelve a entrar en el mismo telefono deja el dispositivo solo en la sesion nueva', function () {
    entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-viejo']);
    entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-nuevo']);

    $conDispositivo = array_values(array_filter(
        abiertasDe(ReplicaDeEjemplo::MONICA),
        fn ($s): bool => $s->dispositivo() !== null,
    ));

    expect(abiertasDe(ReplicaDeEjemplo::MONICA))->toHaveCount(2)
        ->and($conDispositivo)->toHaveCount(1)
        ->and($conDispositivo[0]->tokenPush()?->valor())->toBe('fcm-nuevo');
});

it('un token que ya tenia otra persona en otra instalacion solo le queda a la sesion nueva', function () {
    // Reinstalar la App da otra instalación, pero Firebase puede devolver el mismo
    // token: si la sesión vieja lo conservara, el push de una persona sonaría en
    // el teléfono de otra.
    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(LUCIA), 'otra-contrasena');
    entrarCon('70741828', ['instalacionId' => TELEFONO_COMPARTIDO, 'plataforma' => 'android', 'tokenPush' => 'fcm-del-telefono']);

    entrarCon('70223344', ['instalacionId' => '9a2b7c10-1d2e-4f3a-8b4c-5d6e7f8a9b0c', 'plataforma' => 'android', 'tokenPush' => 'fcm-del-telefono'])
        ->assertStatus(200);

    expect(abiertasDe(ReplicaDeEjemplo::MONICA)[0]->tokenPush())->toBeNull()
        ->and(abiertasDe(LUCIA)[0]->tokenPush()?->valor())->toBe('fcm-del-telefono');
});
