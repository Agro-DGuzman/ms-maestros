<?php

declare(strict_types=1);

/*
 * En /v1 el secreto del gateway queda detrás de un interruptor: se prende
 * cuando el APIM ya manda la cabecera, o la App se cae. Se decide en cada
 * pedido para que `route:cache` no congele la configuración.
 */

beforeEach(function () {
    config(['ingesta.gateway.secreto' => 'secreto-del-gateway']);
});

it('apagado, /v1 no exige el secreto', function () {
    config(['ingesta.gateway.en_v1' => false]);

    $this->getJson('/v1/version?plataforma=android&version=1.0.0')->assertOk();
});

it('prendido, /v1 sin el secreto responde 403', function () {
    config(['ingesta.gateway.en_v1' => true]);

    $this->getJson('/v1/version?plataforma=android&version=1.0.0')
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(403);
});

it('prendido, /v1 con el secreto pasa', function () {
    config(['ingesta.gateway.en_v1' => true]);

    $this->getJson('/v1/version?plataforma=android&version=1.0.0', ['X-Gateway-Secret' => 'secreto-del-gateway'])->assertOk();
});

/** Lo que la configuración lee de la variable, sin depender del .env de quien corre la batería. */
function enV1Con(string $valor): mixed
{
    putenv("GATEWAY_SECRETO_EN_V1={$valor}");
    $_ENV['GATEWAY_SECRETO_EN_V1'] = $_SERVER['GATEWAY_SECRETO_EN_V1'] = $valor;

    try {
        return (require base_path('config/ingesta.php'))['gateway']['en_v1'];
    } finally {
        putenv('GATEWAY_SECRETO_EN_V1');
        unset($_ENV['GATEWAY_SECRETO_EN_V1'], $_SERVER['GATEWAY_SECRETO_EN_V1']);
    }
}

it('la variable se lee apagada con no, off, 0 o false', function (string $valor) {
    // Con un cast, «no» u «off» se leían como prendido: la App quedaba
    // afuera de /v1 si el APIM todavía no mandaba la cabecera.
    expect(enV1Con($valor))->toBeFalse();
})->with(['no', 'off', '0', 'false', 'NO']);

it('la variable se lee prendida con yes, on, 1 o true', function (string $valor) {
    expect(enV1Con($valor))->toBeTrue();
})->with(['yes', 'on', '1', 'true']);
