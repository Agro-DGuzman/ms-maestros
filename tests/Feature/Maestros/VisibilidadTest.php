<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EnviadorEspia;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

/*
 * La persona de la semilla es p-8f2b1c40, del socio C-004871, del grupo
 * GRP-014 (C-004871, C-004872, C-004873), con celular 70741828.
 */
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    $this->conToken = ['Authorization' => 'Bearer token-bueno'];
});

function cardCodesDe(array $items): array
{
    return array_column($items, 'cardCode');
}

function buscarPorCelular(string $celular): ?string
{
    $resultado = app(Mediator::class)->send(new BuscarPorCelular(Celular::desdeLocalBoliviano($celular)));

    return $resultado->isSuccess ? $resultado->value()->value() : null;
}

it('un socio inactivo no se ve ni se alcanza', function () {
    ReplicaDeEjemplo::socioInactivo('C-004872');

    $this->getJson('/v1/socios/C-004872/propiedades', $this->conToken)
        ->assertStatus(403)
        ->assertJsonPath('error.code', ['ACCESO_DENEGADO']);

    expect(cardCodesDe($this->getJson('/v1/socios', $this->conToken)->json('data.items')))
        ->toBe(['C-004871', 'C-004873']);
});

it('un socio dado de baja no se ve ni se alcanza', function () {
    ReplicaDeEjemplo::socioDadoDeBaja('C-004873');

    $this->getJson('/v1/socios/C-004873/propiedades', $this->conToken)->assertStatus(403);

    expect(cardCodesDe($this->getJson('/v1/socios', $this->conToken)->json('data.items')))
        ->toBe(['C-004871', 'C-004872']);
});

it('sin grupo, la persona ve solo su propio socio', function () {
    // D12: nadie de afuera lo ve, y él no ve a nadie. Un grupo de uno.
    ReplicaDeEjemplo::sinGrupo('C-004871');

    expect(cardCodesDe($this->getJson('/v1/socios', $this->conToken)->json('data.items')))->toBe(['C-004871']);

    $cuenta = $this->getJson('/v1/mi-cuenta', $this->conToken)->assertOk();

    expect($cuenta->json('data.grupoEconomico.id'))->toBe('')
        ->and($cuenta->json('data.grupoEconomico.nombre'))->toBe('')
        ->and(cardCodesDe($cuenta->json('data.grupoEconomico.socios')))->toBe(['C-004871']);

    $this->getJson('/v1/socios/C-004871/propiedades', $this->conToken)->assertOk();
    $this->getJson('/v1/socios/C-004872/propiedades', $this->conToken)->assertStatus(403);
});

it('una persona inactiva no alcanza nada y no tiene contexto', function () {
    ReplicaDeEjemplo::personaInactiva('p-8f2b1c40');

    $this->getJson('/v1/socios/C-004871/propiedades', $this->conToken)->assertStatus(403);

    $resultado = app(Mediator::class)->send(new ObtenerContexto(IdDePersona::desde('p-8f2b1c40')));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});

it('un celular que comparten dos contactos visibles es un numero desconocido', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    expect(buscarPorCelular('70741828'))->toBeNull();

    // Mismo trato que un número que no es de nadie: se responde igual, pero
    // no se manda ningún código.
    $this->postJson('/v1/auth/otp', ['telefono' => '70741828'])->assertStatus(200);

    expect($this->enviador->enviados)->toBe([]);
});

it('si el otro contacto con el mismo numero esta dado de baja, el numero vuelve a servir', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', dadoDeBaja: true);

    expect(buscarPorCelular('70741828'))->toBe('p-8f2b1c40');
});

it('un contacto de un socio dado de baja no puede ingresar', function () {
    ReplicaDeEjemplo::socioDadoDeBaja('C-004871');

    expect(buscarPorCelular('70741828'))->toBeNull();
});
