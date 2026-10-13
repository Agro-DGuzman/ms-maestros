<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EnviadorEspia;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

/*
 * La persona es su número, +59170741828. En la semilla es contacto solo de
 * C-004871 (grupo GRP-014); acá además de C-004872 y C-004873, del mismo grupo,
 * para ver qué pasa cuando uno de sus socios deja de verse.
 */
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9001', cardCode: 'C-004872');
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9002', cardCode: 'C-004873');
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso(ReplicaDeEjemplo::MONICA));
    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(ReplicaDeEjemplo::MONICA), 'la-contrasena');
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    $this->conToken = ['Authorization' => 'Bearer token-bueno'];
    $this->socios = fn (): array => array_column($this->getJson('/v1/socios', $this->conToken)->json('data.items') ?? [], 'cardCode');
});

it('un socio inactivo no se ve ni se alcanza', function () {
    ReplicaDeEjemplo::socioInactivo('C-004872');

    $this->getJson('/v1/socios/C-004872/propiedades', $this->conToken)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);

    expect(($this->socios)())->toBe(['C-004871', 'C-004873']);
});

it('un socio dado de baja no se ve ni se alcanza', function () {
    ReplicaDeEjemplo::socioDadoDeBaja('C-004873');

    $this->getJson('/v1/socios/C-004873/propiedades', $this->conToken)->assertStatus(403);

    expect(($this->socios)())->toBe(['C-004871', 'C-004872']);
});

it('un socio sin grupo no aparece y no bloquea', function () {
    // D12: un socio sin grupo no entra en el alcance de nadie.
    ReplicaDeEjemplo::sinGrupo('C-004872');

    expect(($this->socios)())->toBe(['C-004871', 'C-004873']);

    $this->getJson('/v1/socios/C-004872/propiedades', $this->conToken)->assertStatus(403);
    $this->getJson('/v1/mi-cuenta', $this->conToken)->assertOk()->assertJsonPath('data.grupoEconomico.id', 'GRP-014');
});

it('si todos sus socios quedan sin grupo no tiene contexto', function () {
    foreach (['C-004871', 'C-004872', 'C-004873'] as $socio) {
        ReplicaDeEjemplo::sinGrupo($socio);
    }

    $this->getJson('/v1/mi-cuenta', $this->conToken)
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['CONTACTO_NO_ENCONTRADO']);
});

it('un contacto inactivo deja de sumar su socio', function () {
    ReplicaDeEjemplo::personaInactiva('p-8f2b1c40');

    $this->getJson('/v1/socios/C-004871/propiedades', $this->conToken)->assertStatus(403);

    expect(($this->socios)())->toBe(['C-004872', 'C-004873']);
});

it('un numero en socios de dos grupos no recibe codigo', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9003');

    // Mismo trato que un número que no es de nadie: se responde igual, pero
    // no se manda ningún código.
    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    expect($this->enviador->enviados)->toBe([]);
});

it('un contacto dado de baja en otro grupo no bloquea el numero', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9003', dadoDeBaja: true);

    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    expect($this->enviador->enviados)->toHaveCount(1);
});
