<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\ConfiguracionDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    ConfiguracionDeEjemplo::sembrar();
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
});

const AUTENTICADO = ['Authorization' => 'Bearer token-bueno'];

it('/version es publica y exige actualizar por debajo de la minima', function () {
    // Pública a propósito: una versión incompatible con el ingreso tiene que
    // poder enterarse de que debe actualizar sin haber entrado.
    $respuesta = $this->getJson('/v1/version?plataforma=android&version=1.7.2')->assertStatus(200);

    expect($respuesta->json('data.estado'))->toBe('actualizacion_obligatoria')
        ->and($respuesta->json('data.permiteContinuar'))->toBeFalse()
        ->and($respuesta->json('data.versionConsultada'))->toBe('1.7.2')
        ->and($respuesta->json('data.titulo'))->toBeString()
        ->and($respuesta->headers->get('Cache-Control'))->toContain('max-age=300');
});

it('/version al dia no avisa nada', function () {
    $respuesta = $this->getJson('/v1/version?plataforma=android&version=2.1.0')->assertStatus(200);

    expect($respuesta->json('data.estado'))->toBe('vigente')
        ->and($respuesta->json('data.permiteContinuar'))->toBeTrue()
        ->and($respuesta->json('data.titulo'))->toBeNull()
        ->and($respuesta->json('data.mensaje'))->toBeNull();
});

it('/version rechaza una version sin formato semantico', function () {
    $this->getJson('/v1/version?plataforma=android&version=2.1')
        ->assertStatus(400)
        ->assertJsonPath('error.structuredMessage.0.campo', 'version');
});

it('/bancos lista las cuentas con su titular', function () {
    $respuesta = $this->getJson('/v1/bancos', AUTENTICADO)->assertStatus(200);

    expect($respuesta->json('data.items'))->toBe([
        ['banco' => 'Banco Nacional de Bolivia', 'numeroCuenta' => '10000006816033'],
        ['banco' => 'Banco BISA', 'numeroCuenta' => '701-5032719-3-81'],
    ])
        ->and($respuesta->json('data.titular.nit'))->toBe('1013879029')
        ->and($respuesta->headers->get('ETag'))->not->toBeNull();
});

it('/bancos responde 304 sin cuerpo si la App ya tiene la misma lista', function () {
    $etag = (string) $this->getJson('/v1/bancos', AUTENTICADO)->headers->get('ETag');

    $respuesta = $this->getJson('/v1/bancos', AUTENTICADO + ['If-None-Match' => $etag])->assertStatus(304);

    expect($respuesta->getContent())->toBe('');
});

it('/bancos cambia de ETag cuando cambian las cuentas, sin reiniciar nada', function () {
    $antes = $this->getJson('/v1/bancos', AUTENTICADO)->headers->get('ETag');

    // Antes un cambio era una revisión nueva del contenedor; ahora es un
    // UPDATE con el proceso vivo, y la App no puede seguir con la lista vieja.
    ConfiguracionDeEjemplo::cuentas([['banco' => 'Banco Unión', 'cuenta' => '089489-001-1', 'orden' => 1]]);

    $despues = $this->getJson('/v1/bancos', AUTENTICADO);

    expect($despues->headers->get('ETag'))->not->toBe($antes)
        ->and($despues->json('data.items.0.banco'))->toBe('Banco Unión');
});

it('/bancos sin cuentas responde un problema nuestro, no una lista vacia', function () {
    ConfiguracionDeEjemplo::cuentas([]);

    $this->getJson('/v1/bancos', AUTENTICADO)
        ->assertStatus(500)
        ->assertJsonPath('error.code', ['CONFIGURACION_INCOMPLETA']);
});

it('/contactos/atencion-al-cliente devuelve el enlace de WhatsApp', function () {
    $this->getJson('/v1/contactos/atencion-al-cliente', AUTENTICADO)
        ->assertStatus(200)
        ->assertJsonPath('data.whatsappUrl', 'https://wa.me/59167701468');
});

it('bancos y atencion al cliente piden token', function (string $ruta) {
    $this->getJson($ruta)->assertStatus(401)->assertJsonPath('error.code', ['TOKEN_INVALIDO']);
})->with(['/v1/bancos', '/v1/contactos/atencion-al-cliente']);
