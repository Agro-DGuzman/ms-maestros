<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
});

it('arma el contexto con la persona, su grupo y todos los socios del grupo', function () {
    $resultado = app(Mediator::class)->send(new ObtenerContexto(IdDePersona::desde('p-8f2b1c40')));

    expect($resultado->isSuccess)->toBeTrue();

    $contexto = $resultado->value();
    expect($contexto)->toBeInstanceOf(ContextoDeContacto::class)
        ->and($contexto->nombre)->toBe('Monica Salvatierra')
        ->and($contexto->iniciales)->toBe('MS')
        ->and($contexto->celular)->toBe('+591 707 41 828')
        ->and($contexto->grupoId)->toBe('GRP-014')
        ->and($contexto->grupoNombre)->toBe('Grupo Monasterio')
        ->and($contexto->socios)->toHaveCount(3)
        ->and($contexto->socios[0]['cardCode'])->toBe('C-004871')
        ->and($contexto->socios[0]['iniciales'])->toBe('SM')
        ->and($contexto->socios[0]['cantidadPropiedades'])->toBe(0);
});

it('falla con NOT_FOUND si la persona no existe', function () {
    $resultado = app(Mediator::class)->send(new ObtenerContexto(IdDePersona::desde('p-nadie')));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});

it('resuelve la persona a partir del celular', function () {
    $resultado = app(Mediator::class)->send(
        new BuscarPorCelular(Celular::desdeLocalBoliviano('70741828')),
    );

    expect($resultado->value()->value())->toBe('p-8f2b1c40');
});

it('no encuentra persona para un celular desconocido', function () {
    $resultado = app(Mediator::class)->send(
        new BuscarPorCelular(Celular::desdeLocalBoliviano('79999999')),
    );

    expect($resultado->isFailure())->toBeTrue();
});
