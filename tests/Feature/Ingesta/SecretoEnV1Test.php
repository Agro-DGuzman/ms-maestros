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
