<?php

declare(strict_types=1);

use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\CategoriaDeAviso;

/** A las 23:30 del 16 en Bolivia, que en UTC ya es el 17. */
const AHORA_EN_EL_BORDE = '2026-11-17T03:30:00Z';

function avisoCreadoHace(string $hace, ?string $fechaDelDocumento): AvisoParaPush
{
    return new AvisoParaPush(
        'AV-F-1990',
        CategoriaDeAviso::Factura,
        'C-004871',
        'Factura N° 1990 disponible',
        'Ya puedes ver la factura de Sebastian Monasterio.',
        (new DateTimeImmutable(AHORA_EN_EL_BORDE))->modify("-{$hace}"),
        $fechaDelDocumento,
    );
}

it('decide si un aviso es viejo con las 24 horas y la fecha del documento en hora de Bolivia', function (string $hace, ?string $fecha, bool $viejo) {
    expect(avisoCreadoHace($hace, $fecha)->esViejo(new DateTimeImmutable(AHORA_EN_EL_BORDE)))->toBe($viejo);
})->with([
    'de hoy en Bolivia, aunque en UTC ya sea mañana' => ['23 hours', '2026-11-16', false],
    'de ayer' => ['23 hours', '2026-11-15', false],
    'de anteayer' => ['23 hours', '2026-11-14', true],
    'leido hace 25 horas, sin fecha' => ['25 hours', null, true],
    'leido hace 1 hora, sin fecha' => ['1 hour', null, false],
    'leido hace 25 horas aunque el documento sea de hoy' => ['25 hours', '2026-11-16', true],
]);

it('ida y vuelta por el arreglo del trabajo', function () {
    $aviso = new AvisoParaPush(
        'AV-C-A-0999',
        CategoriaDeAviso::Catalogo,
        null,
        'Nuevo en el catálogo · Gliforte 68 SG',
        'Gliforte 68 SG (Herbicidas) ya está disponible en el catálogo.',
        new DateTimeImmutable('2026-11-16T14:00:00.123456Z'),
        null,
    );

    expect(AvisoParaPush::desdeArreglo($aviso->aArreglo()))->toEqual($aviso);
});
