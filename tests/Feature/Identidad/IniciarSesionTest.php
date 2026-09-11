<?php

declare(strict_types=1);

use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

final class EmisorFalso implements EmisorDeToken
{
    public bool $caido = false;

    /** @var list<string> */
    public array $pedidos = [];

    public function emitirPara(IdDePersona $persona, string $contrasena): ResultWithValue
    {
        $this->pedidos[] = $persona->value();

        if ($this->caido) {
            return ResultWithValue::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'El proveedor de identidad no responde'),
            );
        }

        return ResultWithValue::of(new TokenEmitido('jwt-de-prueba', 'refresh-de-prueba', 900));
    }

    public function renovar(string $refreshToken): ResultWithValue
    {
        return ResultWithValue::of(new TokenEmitido('jwt-2', 'refresh-2', 900));
    }

    public function revocar(string $refreshToken): Result
    {
        return Result::success();
    }
}

final class EnviadorQueRecuerda implements EnviadorDeDesafio
{
    public string $ultimoCodigo = '';

    public function enviar(Celular $a, string $digitos): void
    {
        $this->ultimoCodigo = $digitos;
    }
}

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $this->emisor = new EmisorFalso();
    $this->enviador = new EnviadorQueRecuerda();
    $this->app->instance(EmisorDeToken::class, $this->emisor);
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);

    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde('p-8f2b1c40'), 'la-contrasena');
});

function pedirDesafio(): string
{
    return (string) test()->postJson('/v1/auth/otp', ['telefono' => '70741828'])->json('data.idDeDesafio');
}

it('entrega token, sesion y contexto con el codigo correcto', function () {
    $id = pedirDesafio();

    $respuesta = $this->postJson('/v1/auth/login', [
        'idDeDesafio' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
        'instalacionId' => 'inst-1',
        'plataforma' => 'android',
    ])->assertStatus(200);

    expect($respuesta->json('success'))->toBeTrue()
        ->and($respuesta->json('data.token'))->toBe('jwt-de-prueba')
        ->and($respuesta->json('data.refreshToken'))->toBe('refresh-de-prueba')
        ->and($respuesta->json('data.contexto.nombre'))->toBe('Monica Salvatierra')
        ->and($respuesta->json('data.contexto.grupoEconomico.socios'))->toHaveCount(3)
        ->and($this->emisor->pedidos)->toBe(['p-8f2b1c40']);
});

it('registra la sesion con su dispositivo', function () {
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', [
        'idDeDesafio' => $id,
        'codigo' => $this->enviador->ultimoCodigo,
        'instalacionId' => 'inst-1',
        'plataforma' => 'ios',
    ]);

    $abiertas = app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40'));

    expect($abiertas)->toHaveCount(1)
        ->and($abiertas[0]->dispositivo()->instalacion()->value())->toBe('inst-1');
});

it('rechaza el codigo equivocado con 422 y no pide token', function () {
    $id = pedirDesafio();

    $this->postJson('/v1/auth/login', ['idDeDesafio' => $id, 'codigo' => '0000'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);

    expect($this->emisor->pedidos)->toBe([]);
});

it('no deja usar el mismo desafio dos veces', function () {
    $id = pedirDesafio();
    $codigo = $this->enviador->ultimoCodigo;

    $this->postJson('/v1/auth/login', ['idDeDesafio' => $id, 'codigo' => $codigo])->assertStatus(200);
    $this->postJson('/v1/auth/login', ['idDeDesafio' => $id, 'codigo' => $codigo])->assertStatus(422);
});

it('un desafio inexistente responde el mismo CODIGO_INVALIDO', function () {
    $this->postJson('/v1/auth/login', ['idDeDesafio' => 'no-existe', 'codigo' => '1234'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ['CODIGO_INVALIDO']);
});

it('si Keycloak no responde devuelve 500 y no abre sesion', function () {
    $id = pedirDesafio();
    $this->emisor->caido = true;

    $this->postJson('/v1/auth/login', ['idDeDesafio' => $id, 'codigo' => $this->enviador->ultimoCodigo])
        ->assertStatus(500)
        ->assertJsonPath('error.type', 'PROBLEM');

    expect(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toBe([]);
});
