<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;

it('conserva el oid de entra', function () {
    expect(IdDeOperador::desdeOid('8f2b1c40-1d2e-4a5b-9c8d-7e6f5a4b3c2d')->valor)
        ->toBe('8f2b1c40-1d2e-4a5b-9c8d-7e6f5a4b3c2d');
});

it('recorta los espacios', function () {
    expect(IdDeOperador::desdeOid('  abc-123  ')->valor)->toBe('abc-123');
});

it('rechaza un oid vacio', function (string $malo) {
    expect(fn () => IdDeOperador::desdeOid($malo))
        ->toThrow(InvalidArgumentException::class, 'OPERADOR_SIN_OID');
})->with(['', '   ']);

it('dos ids con el mismo oid son iguales', function () {
    expect(IdDeOperador::desdeOid('abc')->esIgualA(IdDeOperador::desdeOid('abc')))->toBeTrue()
        ->and(IdDeOperador::desdeOid('abc')->esIgualA(IdDeOperador::desdeOid('otro')))->toBeFalse();
});
