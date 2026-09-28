<?php

declare(strict_types=1);

use BackOffice\Domain\Catalogo\CambioDeCatalogo;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cambioDe(int $idProducto, ?string $itemCode, string $nuevo, string $momento): CambioDeCatalogo
{
    return CambioDeCatalogo::nuevo(
        operador: new Operador(IdDeOperador::desdeOid('oid-1'), 'Ana Suárez', 'ana@agropartners.com.bo', Permiso::todos()),
        idProducto: $idProducto,
        itemCode: $itemCode,
        campo: 'imagen_url',
        anterior: null,
        nuevo: $nuevo,
        direccionIp: '190.129.4.7',
        ocurrioEl: new DateTimeImmutable($momento),
    );
}

it('devuelve el historial de un producto del mas reciente al mas viejo', function () {
    $registro = app(RegistroDeCambiosDelCatalogo::class);
    $registro->asentar(cambioDe(7, 'A-0142', 'https://x.bo/1.jpg', '2026-09-28T10:00:00+00:00'));
    $registro->asentar(cambioDe(7, 'A-0142', 'https://x.bo/2.jpg', '2026-09-28T11:00:00+00:00'));

    expect(array_map(static fn (CambioDeCatalogo $c): ?string => $c->nuevo, $registro->historialDe(7)))
        ->toBe(['https://x.bo/2.jpg', 'https://x.bo/1.jpg']);
});

it('el historial de un producto no trae los de otro', function () {
    $registro = app(RegistroDeCambiosDelCatalogo::class);
    $registro->asentar(cambioDe(7, 'A-0142', 'https://x.bo/1.jpg', '2026-09-28T10:00:00+00:00'));
    $registro->asentar(cambioDe(8, 'S-0101', 'https://x.bo/2.jpg', '2026-09-28T11:00:00+00:00'));

    expect($registro->historialDe(8))->toHaveCount(1)
        ->and($registro->historialDe(8)[0]->itemCode)->toBe('S-0101');
});

it('guarda un item_code nulo', function () {
    $registro = app(RegistroDeCambiosDelCatalogo::class);
    $registro->asentar(cambioDe(9, null, 'https://x.bo/1.jpg', '2026-09-28T10:00:00+00:00'));

    expect($registro->historialDe(9)[0]->itemCode)->toBeNull()
        ->and($registro->historialDe(9)[0]->operador)->toBe('Ana Suárez');
});

it('el registro no expone forma de modificar ni borrar', function () {
    $metodos = array_map(
        static fn (ReflectionMethod $m): string => $m->name,
        (new ReflectionClass(RegistroDeCambiosDelCatalogo::class))->getMethods(),
    );

    expect($metodos)->toBe(['asentar', 'historialDe']);
});
