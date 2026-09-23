<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Tests\Dobles\VerificadorFalso;

beforeEach(function () {
    config([
        'app_movil.versiones.android' => ['minima' => '1.8.0', 'recomendada' => '2.1.0', 'tienda' => 'https://play.google.com/store/apps/details?id=bo.com.agropartners.socio'],
        'app_movil.bancos.cuentas' => 'Banco Nacional de Bolivia|10000006816033;Banco BISA|701-5032719-3-81',
        'app_movil.bancos.titular_nit' => '1013879029',
        'app_movil.atencion.telefono' => '67701468',
    ]);
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

it('/bancos cambia de ETag cuando cambian las cuentas', function () {
    $antes = $this->getJson('/v1/bancos', AUTENTICADO)->headers->get('ETag');

    // Cambiar una variable en la nube es una revisión nueva, o sea un proceso
    // nuevo: se simula con una aplicación recién arrancada.
    $this->refreshApplication();
    config([
        'app_movil.bancos.cuentas' => 'Banco Unión|089489-001-1',
        'app_movil.bancos.titular_nit' => '1013879029',
    ]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));

    expect($this->getJson('/v1/bancos', AUTENTICADO)->headers->get('ETag'))->not->toBe($antes);
});

it('/bancos sin configurar responde un problema nuestro, no una lista vacia', function () {
    config(['app_movil.bancos.cuentas' => '']);

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
