<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Propiedades\PropiedadDelSocio;
use Maestros\Application\Propiedades\PropiedadesDeSocios;
use Maestros\Domain\Socios\CodigoDeSocio;
use Tests\Soporte\PropiedadesDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    PropiedadesDeEjemplo::sembrar();
    $this->propiedades = app(PropiedadesDeSocios::class);
});

/** @return list<array{string, string}> */
function idYNombre(array $propiedades): array
{
    return array_map(static fn (PropiedadDelSocio $p): array => [$p->id, $p->nombre], $propiedades);
}

it('lista solo las activas del socio, por nombre', function () {
    // PROP-003 es del mismo socio pero está dada de baja.
    expect(idYNombre($this->propiedades->deSocio(CodigoDeSocio::desde('C-004871'))))->toBe([
        ['PROP-007', 'Lote 07 · Cuatro Cañadas'],
        ['PROP-014', 'Lote 14 · San Julián'],
    ]);
});

it('un socio sin propiedades da lista vacia', function () {
    expect($this->propiedades->deSocio(CodigoDeSocio::desde('C-004873')))->toBe([]);
});

it('cuenta las activas de cada socio pedido', function () {
    $conteo = $this->propiedades->contarPorSocio([
        CodigoDeSocio::desde('C-004871'),
        CodigoDeSocio::desde('C-004872'),
        CodigoDeSocio::desde('C-004873'),
    ]);

    // Un socio sin propiedades no figura: quien consulta completa con 0.
    expect($conteo)->toEqualCanonicalizing(['C-004871' => 2, 'C-004872' => 1])
        ->and(array_keys($conteo))->toEqualCanonicalizing(['C-004871', 'C-004872']);
});

it('contar una lista vacia no consulta nada', function () {
    expect($this->propiedades->contarPorSocio([]))->toBe([]);
});

it('no cuenta socios que no se pidieron', function () {
    expect($this->propiedades->contarPorSocio([CodigoDeSocio::desde('C-004871')]))->toBe(['C-004871' => 2]);
});
