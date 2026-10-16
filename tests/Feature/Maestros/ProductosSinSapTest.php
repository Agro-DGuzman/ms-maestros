<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

/**
 * La semilla no tiene origen en ninguno de sus seis productos: Gliforte recibe
 * uno para que el comando tenga qué dejar afuera.
 */
beforeEach(function () {
    CatalogoDeEjemplo::sembrar();

    DB::table(CatalogoDeEjemplo::tabla('producto'))
        ->where('id_producto', CatalogoDeEjemplo::idDe('Gliforte 68 SG'))
        ->update(['origen_esquema' => 'AGRO_P6']);
});

function productosConBaja(): int
{
    return DB::table(CatalogoDeEjemplo::tabla('producto'))->whereNotNull('dado_de_baja_el')->count();
}

it('sin opciones lista los que nunca llegaron de SAP y no escribe nada', function () {
    // «Dado de baja» ya está dado de baja en la semilla, y Gliforte sí llegó.
    $this->artisan('maestros:productos-sin-sap')
        ->assertSuccessful()
        ->expectsOutputToContain('Sorgo Jisunú 101')
        ->expectsOutputToContain('Atrazina 90 WG')
        ->expectsOutputToContain('Sin código SAP todavía')
        ->expectsOutputToContain('Sin categoría todavía')
        ->doesntExpectOutputToContain('Gliforte 68 SG')
        ->doesntExpectOutputToContain('Dado de baja')
        ->run();

    expect(productosConBaja())->toBe(1);
});

it('con --dar-de-baja les da de baja solo a esos', function () {
    $this->artisan('maestros:productos-sin-sap --dar-de-baja')
        ->assertSuccessful()
        ->expectsOutputToContain('Se dieron de baja 4 productos.')
        ->run();

    $sinBaja = DB::table(CatalogoDeEjemplo::tabla('producto'))->whereNull('dado_de_baja_el')->pluck('nombre')->all();

    expect(productosConBaja())->toBe(5)
        ->and($sinBaja)->toBe(['Gliforte 68 SG'])
        ->and(DB::table(CatalogoDeEjemplo::tabla('producto'))->where('nombre', 'Atrazina 90 WG')->value('vigente_desde'))
        ->not->toBeNull();
});

it('sin nada que dar de baja lo dice', function () {
    DB::table(CatalogoDeEjemplo::tabla('producto'))->update(['origen_esquema' => 'AGRO_P6']);

    $this->artisan('maestros:productos-sin-sap')
        ->assertSuccessful()
        ->expectsOutputToContain('Todos los productos llegaron de SAP.')
        ->run();

    expect(productosConBaja())->toBe(1);
});
