<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Infrastructure\Persistence\ContactoRecord;
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

it('crea el usuario en el directorio y guarda la contrasena cifrada', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');

    expect(app(Mediator::class)->send(new HabilitarPersona($persona))->isSuccess)->toBeTrue()
        ->and($this->directorio->estaActivo($persona))->toBeTrue();

    $guardada = app(BovedaDeContrasenas::class)->leer($persona);

    expect($guardada)->toBeString()
        ->and(strlen((string) $guardada))->toBeGreaterThanOrEqual(24)
        ->and($guardada)->toBe($this->directorio->usuarios['p-8f2b1c40']);
});

it('no guarda contrasena si el directorio falla', function () {
    $this->directorio->caido = true;
    $persona = IdDePersona::desde('p-8f2b1c40');

    $resultado = app(Mediator::class)->send(new HabilitarPersona($persona));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE')
        ->and(app(BovedaDeContrasenas::class)->leer($persona))->toBeNull();
});

it('no habilita a quien no tiene un celular con el que pueda entrar', function () {
    // La columna es NOT NULL y el objeto de valor valida al leer, asi que un
    // celular inservible solo llega si alguien escribio la fila por fuera del
    // dominio. Cuando pasa, el operador tiene que leer que hacer, no un stack.
    ContactoRecord::query()->where('id_de_persona', 'p-8f2b1c40')->update(['celular' => '12345']);

    $resultado = app(Mediator::class)->send(new HabilitarPersona(IdDePersona::desde('p-8f2b1c40')));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CELULAR_INVALIDO')
        ->and($resultado->error->description)->toContain('SAP')
        ->and($resultado->error->description)->toContain('importar');
});

it('no crea el usuario en el directorio si el celular no sirve', function () {
    ContactoRecord::query()->where('id_de_persona', 'p-8f2b1c40')->update(['celular' => '12345']);

    app(Mediator::class)->send(new HabilitarPersona(IdDePersona::desde('p-8f2b1c40')));

    expect($this->directorio->usuarios)->toBe([])
        ->and(app(BovedaDeContrasenas::class)->leer(IdDePersona::desde('p-8f2b1c40')))->toBeNull();
});

it('rechaza habilitar a alguien que no existe en la replica', function () {
    $resultado = app(Mediator::class)->send(new HabilitarPersona(IdDePersona::desde('p-fantasma')));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});
