<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\FiltroDeEnlaces;
use Maestros\Application\Productos\ProductoAdministrable;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    $this->catalogo = app(CatalogoAdministrable::class);
});

/** @return list<string> */
function nombresDe(array $productos): array
{
    return array_map(static fn (ProductoAdministrable $p): string => $p->nombre, $productos);
}

it('lista todos, tambien los que la App no ve', function () {
    // El área tiene que poder cargar la imagen de un producto antes de que
    // tenga código o categoría: la lista no se limita a lo visible.
    $productos = $this->catalogo->listar(null, FiltroDeEnlaces::Todos);
    $porNombre = array_combine(nombresDe($productos), $productos);

    expect(nombresDe($productos))->toBe([
        'Atrazina 90 WG', 'Dado de baja', 'Gliforte 68 SG',
        'Sin categoría todavía', 'Sin código SAP todavía', 'Sorgo Jisunú 101',
    ])
        ->and($porNombre['Gliforte 68 SG']->visibleEnApp())->toBeTrue()
        ->and($porNombre['Dado de baja']->porQueNoSeVe())->toBe('Está dado de baja.')
        ->and($porNombre['Sin código SAP todavía']->porQueNoSeVe())->toBe('No tiene código de artículo.')
        ->and($porNombre['Sin categoría todavía']->porQueNoSeVe())->toBe('No tiene categoría.')
        ->and($porNombre['Sin categoría todavía']->visibleEnApp())->toBeFalse();
});

it('busca por nombre o por codigo, sin distinguir mayusculas', function () {
    expect(nombresDe($this->catalogo->listar('GLIFORTE', FiltroDeEnlaces::Todos)))->toBe(['Gliforte 68 SG'])
        ->and(nombresDe($this->catalogo->listar('a-0142', FiltroDeEnlaces::Todos)))->toBe(['Gliforte 68 SG']);
});

it('filtra los que no tienen imagen', function () {
    expect(nombresDe($this->catalogo->listar(null, FiltroDeEnlaces::SinImagen)))->not->toContain('Gliforte 68 SG')
        ->and($this->catalogo->listar(null, FiltroDeEnlaces::SinImagen))->toHaveCount(5);
});

it('filtra los que no tienen ningun documento', function () {
    // «Dado de baja» tiene ficha y el sorgo, registro sanitario: con uno alcanza.
    expect(nombresDe($this->catalogo->listar(null, FiltroDeEnlaces::SinDocumentos)))
        ->toBe(['Atrazina 90 WG', 'Sin categoría todavía', 'Sin código SAP todavía']);
});

it('buscar un id que no existe es null', function () {
    expect($this->catalogo->buscar(999999))->toBeNull();
});

it('buscar trae los enlaces del producto', function () {
    $gliforte = $this->catalogo->buscar(CatalogoDeEjemplo::idDe('Gliforte 68 SG'));

    expect($gliforte?->itemCode)->toBe('A-0142')
        ->and($gliforte?->categoria)->toBe('Herbicidas')
        ->and($gliforte?->enlaces->imagen)->toBe('https://cdn.agropartners.com.bo/productos/A-0142.webp')
        ->and($gliforte?->enlaces->documentosCargados())->toBe(2);
});
