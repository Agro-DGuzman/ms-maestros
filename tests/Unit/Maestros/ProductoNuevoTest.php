<?php

declare(strict_types=1);

use Maestros\Application\Eventos\ProductoNuevo;
use Maestros\Application\Ingesta\ProductoAnunciado;
use Tests\Soporte\Contrato;

function anunciado(string $nombre = 'Gliforte 68 SG'): ProductoAnunciado
{
    return new ProductoAnunciado('A-0999', $nombre, 'herbicidas', 'Herbicidas');
}

it('arma el sobre de maestros.producto.nuevo y valida contra el contrato', function () {
    $evento = ProductoNuevo::de(anunciado(), new DateTimeImmutable('2026-11-16T08:30:00-04:00'));
    $sobre = $evento->sobre;

    expect($evento->tipo)->toBe('maestros.producto.nuevo')
        ->and($sobre['ocurridoEn'])->toBe('2026-11-16T12:30:00.000000Z')
        ->and($sobre['clave'])->toBe(['objeto' => 'producto', 'itemCode' => 'A-0999'])
        ->and($sobre['datos'])->toBe([
            'itemCode' => 'A-0999',
            'nombre' => 'Gliforte 68 SG',
            'categoria' => ['codigo' => 'herbicidas', 'nombre' => 'Herbicidas'],
        ])
        ->and(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'ProductoNuevo'))->toBe([]);
});

it('un itemCode distinto en la clave no valida', function () {
    $sobre = ProductoNuevo::de(anunciado(), new DateTimeImmutable('2026-11-16T12:30:00Z'))->sobre;
    $sobre['clave']['itemCode'] = str_repeat('X', 51);

    expect(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'ProductoNuevo'))->not->toBe([]);
});

it('un nombre de mas de 200 caracteres se corta y sigue validando', function () {
    $sobre = ProductoNuevo::de(anunciado(str_repeat('ñ', 250)), new DateTimeImmutable('2026-11-16T12:30:00Z'))->sobre;

    expect(mb_strlen($sobre['datos']['nombre']))->toBe(200)
        ->and(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'ProductoNuevo'))->toBe([]);
});
