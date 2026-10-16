<?php

declare(strict_types=1);

use Maestros\Application\Ingesta\CategoriaIngresada;
use Tests\Soporte\ProductoDeIngesta;

it('arma la formulacion como la del catalogo', function (?string $codigo, ?string $descripcion, ?string $esperada) {
    $producto = ProductoDeIngesta::ingresado([
        'formulacionCodigo' => $codigo,
        'formulacionDescripcion' => $descripcion,
    ]);

    expect($producto->formulacion())->toBe($esperada);
})->with([
    'los dos' => ['SC', 'SUSPENSION CONCENTRADA', 'SUSPENSION CONCENTRADA (SC)'],
    'solo el codigo' => ['SC', null, 'SC'],
    'solo la descripcion' => [null, 'SUSPENSION CONCENTRADA', 'SUSPENSION CONCENTRADA'],
    'ninguno' => [null, null, null],
]);

/**
 * La clave primaria de `producto_cultivo` en SQL Server no distingue
 * mayúsculas: dos cultivos que solo difieren en eso harían fallar el envío.
 * Los acentos sí distinguen, igual que la colación `CI_AS`.
 */
it('deja cada cultivo una vez sin distinguir mayusculas', function () {
    $producto = ProductoDeIngesta::ingresado(['cultivos' => ['SOYA', 'Soya', 'MAÍZ', 'MAIZ']]);

    expect($producto->cultivosSinRepetir())->toBe(['SOYA', 'MAÍZ', 'MAIZ']);
});

it('la categoria normaliza el codigo', function () {
    expect(CategoriaIngresada::de(' INSECTICIDAS ', 'X')->codigo)->toBe('insecticidas');
});
