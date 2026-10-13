<?php

declare(strict_types=1);

use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Contactos\PaginaDeContactos;

it('un texto en blanco equivale a no filtrar', function (?string $vacio) {
    expect(CriterioDeBusqueda::de($vacio)->texto)->toBeNull();
})->with([null, '', '   ']);

it('recorta el texto', function () {
    expect(CriterioDeBusqueda::de('  Monica  ')->texto)->toBe('Monica');
});

it('calcula el salto a partir de la pagina', function () {
    expect(CriterioDeBusqueda::de(null, 1, 25)->salto())->toBe(0)
        ->and(CriterioDeBusqueda::de(null, 3, 25)->salto())->toBe(50);
});

it('rechaza una pagina menor a uno', function () {
    expect(fn () => CriterioDeBusqueda::de(null, 0, 25))
        ->toThrow(InvalidArgumentException::class, 'PAGINA_INVALIDA');
});

it('rechaza un tamano de pagina fuera de rango', function (int $malo) {
    expect(fn () => CriterioDeBusqueda::de(null, 1, $malo))
        ->toThrow(InvalidArgumentException::class, 'TAMANO_DE_PAGINA_INVALIDO');
})->with([0, 101]);

it('redondea hacia arriba el total de paginas', function () {
    expect((new PaginaDeContactos([], 51, 1, 25))->totalDePaginas())->toBe(3)
        ->and((new PaginaDeContactos([], 50, 1, 25))->totalDePaginas())->toBe(2);
});

it('sin resultados hay una sola pagina', function () {
    expect((new PaginaDeContactos([], 0, 1, 25))->totalDePaginas())->toBe(1);
});
