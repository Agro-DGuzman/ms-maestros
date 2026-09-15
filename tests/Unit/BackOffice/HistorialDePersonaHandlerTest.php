<?php

declare(strict_types=1);

use BackOffice\Application\Bitacora\HistorialDePersona\HistorialDePersona;
use BackOffice\Application\Bitacora\HistorialDePersona\HistorialDePersonaHandler;
use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use Tests\Dobles\BitacoraEnMemoria;

function asientoPara(string $idDePersona, AccionDeAcceso $accion): AsientoDeBitacora
{
    return AsientoDeBitacora::nuevo(
        operador: new Operador(IdDeOperador::desdeOid('oid-77'), 'Mónica', 'monica@agropartners.com.bo'),
        idDePersona: $idDePersona,
        accion: $accion,
        direccionIp: '190.129.4.7',
        ocurrioEl: new DateTimeImmutable('2026-09-15T14:30:00+00:00'),
    );
}

it('devuelve solo los asientos de esa persona', function () {
    $bitacora = new BitacoraEnMemoria;
    $bitacora->asentar(asientoPara('p-1', AccionDeAcceso::Concedio));
    $bitacora->asentar(asientoPara('p-2', AccionDeAcceso::Concedio));
    $bitacora->asentar(asientoPara('p-1', AccionDeAcceso::Revoco));

    $resultado = (new HistorialDePersonaHandler($bitacora))->handle(new HistorialDePersona('p-1'));

    expect($resultado->isSuccess)->toBeTrue()
        ->and($resultado->value())->toHaveCount(2);
});

it('una persona sin movimientos devuelve lista vacia y no un fallo', function () {
    $resultado = (new HistorialDePersonaHandler(new BitacoraEnMemoria))
        ->handle(new HistorialDePersona('p-inexistente'));

    expect($resultado->isSuccess)->toBeTrue()
        ->and($resultado->value())->toBe([]);
});
