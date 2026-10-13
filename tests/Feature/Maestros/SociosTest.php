<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\PropiedadesDeEjemplo;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso(ReplicaDeEjemplo::MONICA));
    // Mónica es contacto de C-004871 y, acá, también de C-004872.
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');
});

it('lista los socios que alcanza la persona del token', function () {
    $respuesta = $this->getJson('/v1/socios', ['Authorization' => 'Bearer token-bueno'])
        ->assertStatus(200);

    expect($respuesta->json('data.items'))->toHaveCount(2)
        ->and($respuesta->json('data.items.0'))->toHaveKeys(['cardCode', 'razonSocial', 'iniciales', 'cantidadPropiedades'])
        ->and($respuesta->json('error'))->toBeNull();
});

it('son los mismos socios que muestra mi-cuenta', function () {
    // El selector de la App y *Mi perfil* muestran la misma lista: si difieren,
    // el usuario ve un socio en un lado que en el otro no existe.
    $cabeceras = ['Authorization' => 'Bearer token-bueno'];

    $socios = $this->getJson('/v1/socios', $cabeceras)->json('data.items');
    $miCuenta = $this->getJson('/v1/mi-cuenta', $cabeceras)->json('data.grupoEconomico.socios');

    expect($socios)->not->toBeEmpty()
        ->and($socios)->toBe($miCuenta);
});

it('cantidadPropiedades es el largo de la lista de cada socio', function () {
    // El selector dice «2 propiedades» y el paso 2 de la visita tiene que
    // mostrar esas dos: si difieren, el socio ve una cuenta que no cierra.
    PropiedadesDeEjemplo::sembrar();
    $cabeceras = ['Authorization' => 'Bearer token-bueno'];

    $socios = $this->getJson('/v1/socios', $cabeceras)->json('data.items');

    foreach ($socios as $socio) {
        $lista = $this->getJson("/v1/socios/{$socio['cardCode']}/propiedades", $cabeceras)->json('data.items');

        expect($socio['cantidadPropiedades'])->toBe(count($lista));
    }

    // Sin esto, 0 contra una lista vacía pasaría en verde sin probar nada.
    expect(max(array_column($socios, 'cantidadPropiedades')))->toBeGreaterThan(0);
});

it('responde 401 sin cabecera Authorization', function () {
    $this->getJson('/v1/socios')
        ->assertStatus(401)
        ->assertJsonPath('error.code', ['TOKEN_INVALIDO'])
        ->assertJsonPath('data', null);
});
