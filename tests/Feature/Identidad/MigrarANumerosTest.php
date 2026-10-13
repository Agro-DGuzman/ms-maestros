<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Infrastructure\Persistence\ContactoRecord;
use Tests\Dobles\DirectorioFalso;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// Antes del cambio, Mónica tenía credencial con su id por contacto. Después,
// esa credencial no sirve para entrar: la migración la pasa a su número.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);

    $vieja = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($vieja, 'la-vieja');
    app(BovedaDeContrasenas::class)->guardar($vieja, 'la-vieja');
    app(SesionRepository::class)->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        $vieja,
        null,
        new DateTimeImmutable('2026-10-01T12:00:00Z'),
        new DateTimeImmutable('2036-10-01T12:00:00Z'),
    ));
});

it('migra una credencial por contacto al numero y desactiva la vieja', function () {
    $this->artisan('identidad:migrar-a-numeros')->assertExitCode(0);

    $boveda = app(BovedaDeContrasenas::class);

    expect($boveda->leer(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->not->toBeNull()
        ->and($boveda->leer(IdDePersona::desde('p-8f2b1c40')))->toBeNull()
        ->and($this->directorio->estaActivo(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBeTrue()
        ->and($this->directorio->estaActivo(IdDePersona::desde('p-8f2b1c40')))->toBeFalse()
        ->and(app(SesionRepository::class)->abiertasDe(IdDePersona::desde('p-8f2b1c40')))->toBe([]);
});

it('una segunda corrida no hace nada', function () {
    $this->artisan('identidad:migrar-a-numeros');

    $this->artisan('identidad:migrar-a-numeros')
        ->expectsOutputToContain('Migradas 0')
        ->assertExitCode(0);
});

it('un contacto sin celular valido se informa y no se toca', function () {
    ContactoRecord::query()->where('id_de_persona', 'p-8f2b1c40')->update(['celular' => null]);

    // Queda una credencial por contacto: la migración no terminó, y lo dice.
    $this->artisan('identidad:migrar-a-numeros')
        ->expectsOutputToContain('p-8f2b1c40')
        ->expectsOutputToContain('Migradas 0, con error 1')
        ->assertExitCode(1);

    expect(app(BovedaDeContrasenas::class)->leer(IdDePersona::desde('p-8f2b1c40')))->not->toBeNull();
});

it('si no puede habilitar el numero lo informa y sale con error', function () {
    // El número quedó en dos grupos: no se puede habilitar. La vieja se
    // deshabilita igual, pero eso no es una migración hecha.
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    $this->artisan('identidad:migrar-a-numeros')
        ->expectsOutputToContain('NUMERO_EN_VARIOS_GRUPOS')
        ->expectsOutputToContain('Migradas 0, con error 1')
        ->assertExitCode(1);

    expect($this->directorio->estaActivo(IdDePersona::desde('p-8f2b1c40')))->toBeFalse();
});

it('si el directorio no responde sale con error', function () {
    $this->directorio->caido = true;

    $this->artisan('identidad:migrar-a-numeros')
        ->expectsOutputToContain('Migradas 0, con error 1')
        ->assertExitCode(1);
});
