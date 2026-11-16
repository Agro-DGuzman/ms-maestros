<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\CatalogoDeEjemplo;
use Tests\Soporte\Ingesta;
use Tests\Soporte\ProductoDeIngesta;
use Tests\Soporte\TokenDeEntra;

uses(RefreshDatabase::class);

it('la migracion marca como anunciado todo lo que ya existia, visible o no (NP12)', function () {
    $migracion = require database_path('migrations/maestros/2026_11_16_000300_anunciar_productos_nuevos.php');
    $migracion->down();

    CatalogoDeEjemplo::sembrar();
    DB::table(CatalogoDeEjemplo::tabla('producto'))->where('codigo_articulo', 'A-0142')->update(['activo' => false]);

    $migracion->up();

    expect(DB::table(CatalogoDeEjemplo::tabla('producto'))->whereNull('anunciado_el')->count())->toBe(0);

    // El que estaba inactivo vuelve, y no es nuevo.
    TokenDeEntra::configurar();
    test()->putJson('/ingesta/v1/productos/A-0142', ProductoDeIngesta::cuerpo([
        'itemCode' => 'A-0142',
        'activo' => true,
        'vigenteDesde' => '2026-11-17T12:00:00Z',
    ]), Ingesta::cabeceras())->assertStatus(200);

    expect(DB::table('maestros_evento_saliente')->where('tipo', 'maestros.producto.nuevo')->count())->toBe(0);
});
