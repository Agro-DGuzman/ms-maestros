<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

it('saca el usuario del directorio y cierra sus sesiones abiertas', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');

    app(SesionRepository::class)->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        $persona,
        null,
        new DateTimeImmutable('2026-09-14T12:00:00Z'),
        new DateTimeImmutable('2036-09-14T12:00:00Z'),
    ));

    $resultado = app(Mediator::class)->send(new DeshabilitarPersona($persona));

    expect($resultado->isSuccess)->toBeTrue()
        ->and($this->directorio->estaActivo($persona))->toBeFalse()
        ->and(app(SesionRepository::class)->abiertasDe($persona))->toBe([]);
});

it('es idempotente: deshabilitar dos veces no falla', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');

    expect(app(Mediator::class)->send(new DeshabilitarPersona($persona))->isSuccess)->toBeTrue()
        ->and(app(Mediator::class)->send(new DeshabilitarPersona($persona))->isSuccess)->toBeTrue();
});

it('no cierra sesiones si el directorio falla', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');

    app(SesionRepository::class)->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        $persona,
        null,
        new DateTimeImmutable('2026-09-14T12:00:00Z'),
        new DateTimeImmutable('2036-09-14T12:00:00Z'),
    ));

    $this->directorio->caido = true;

    $resultado = app(Mediator::class)->send(new DeshabilitarPersona($persona));

    // Si el bloqueo no ocurrio, cerrar las sesiones da una falsa sensacion de
    // haber quitado el acceso: la persona sigue pudiendo pedir un token nuevo.
    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE')
        ->and(app(SesionRepository::class)->abiertasDe($persona))->toHaveCount(1);
});

it('el comando devuelve 0 al deshabilitar', function () {
    $this->artisan('identidad:deshabilitar', ['persona' => 'p-8f2b1c40'])->assertExitCode(0);
});
