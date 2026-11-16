<?php

declare(strict_types=1);

use Identidad\Domain\Push\CategoriaDeAviso;
use Identidad\Domain\Push\PreferenciasDePush;
use Maestros\Domain\Contactos\IdDePersona;

it('cada interruptor gobierna sus categorias', function (array $apagado, array $cortadas) {
    $preferencias = PreferenciasDePush::de(
        IdDePersona::desde('n-59170741828'),
        ! in_array('facturas', $apagado, true),
        ! in_array('pagos', $apagado, true),
        ! in_array('reportes', $apagado, true),
        ! in_array('catalogo', $apagado, true),
        new DateTimeImmutable('2026-11-16T12:00:00Z'),
    );

    foreach (CategoriaDeAviso::cases() as $categoria) {
        expect($preferencias->permite($categoria))->toBe(! in_array($categoria, $cortadas, true), $categoria->value);
    }
})->with([
    'facturas apagadas cortan factura y vencimiento' => [['facturas'], [CategoriaDeAviso::Factura, CategoriaDeAviso::Vencimiento]],
    'pagos apagados cortan pago' => [['pagos'], [CategoriaDeAviso::Pago]],
    'reportes apagados cortan el informe de visita' => [['reportes'], [CategoriaDeAviso::ReporteCampo]],
    'catalogo apagado corta el catalogo' => [['catalogo'], [CategoriaDeAviso::Catalogo]],
]);

it('sin preferencias guardadas todo esta prendido', function () {
    $preferencias = PreferenciasDePush::todasPrendidas(IdDePersona::desde('n-59170741828'));

    foreach (CategoriaDeAviso::cases() as $categoria) {
        expect($preferencias->permite($categoria))->toBeTrue();
    }

    expect($preferencias->vigenteDesde())->toBeNull();
});
