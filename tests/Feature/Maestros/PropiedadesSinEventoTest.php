<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Tests\Soporte\PropiedadesDeEjemplo;
use Tests\Soporte\SobresDePropiedad;

uses(RefreshDatabase::class);

beforeEach(function () {
    PropiedadesDeEjemplo::sembrar();
    // PROP-014 llegó por un evento: tiene vigencia, y su dueño ya es el real.
    app(Mediator::class)->send(new ProcesarEvento(Sobre::desdeArreglo(SobresDePropiedad::de())));
});

/** @return list<string> */
function idsDePropiedades(): array
{
    return DB::table('maestros_propiedad')->orderBy('id_propiedad')->pluck('id_propiedad')->all();
}

it('lista solo las que nunca llegaron por un evento', function () {
    $this->artisan('maestros:propiedades-sin-evento')
        ->expectsOutputToContain('PROP-007')
        ->doesntExpectOutputToContain('PROP-014')
        ->assertExitCode(0);

    expect(idsDePropiedades())->toBe(['PROP-003', 'PROP-007', 'PROP-014', 'PROP-021', 'PROP-031']);
});

it('con --borrar borra solo esas', function () {
    $this->artisan('maestros:propiedades-sin-evento --borrar')
        ->expectsOutput('Se borraron 4 propiedades.')
        ->assertExitCode(0);

    expect(idsDePropiedades())->toBe(['PROP-014']);
});

it('sin pendientes lo dice', function () {
    DB::table('maestros_propiedad')->whereNull('vigente_desde')->delete();

    $this->artisan('maestros:propiedades-sin-evento')
        ->expectsOutput('Todas las propiedades llegaron por un evento.')
        ->assertExitCode(0);
});
