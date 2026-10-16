<?php

declare(strict_types=1);

use Maestros\Application\Productos\EnlacesDeProducto;
use Maestros\Application\Productos\ProductoAdministrable;

/**
 * El motivo tiene que ser el primero que lo deja afuera, y nombrar a SAP solo
 * cuando el producto llegó de SAP: uno cargado desde la web nunca pasó por ahí.
 */
it('dice por que no se ve, en orden', function (bool $activo, bool $baja, bool $deSap, ?string $codigo, ?string $categoria, ?string $motivo) {
    $p = new ProductoAdministrable(1, $codigo, 'X', $categoria, $activo, $baja, $deSap, new EnlacesDeProducto(null, null, null, null));

    expect($p->porQueNoSeVe())->toBe($motivo);
})->with([
    'dado de baja gana a inactivo' => [false, true, true, 'A-1', 'Herbicidas', 'Está dado de baja.'],
    'inactivo en SAP' => [false, false, true, 'A-1', 'Herbicidas', 'Está inactivo en SAP.'],
    'inactivo de la web' => [false, false, false, 'A-1', 'Herbicidas', 'Está inactivo.'],
    'sin codigo' => [true, false, false, null, 'Herbicidas', 'No tiene código de artículo.'],
    'sin categoria' => [true, false, true, 'A-1', null, 'No tiene categoría.'],
    'visible' => [true, false, true, 'A-1', 'Herbicidas', null],
]);
