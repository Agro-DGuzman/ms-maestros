<?php

declare(strict_types=1);

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;

function unAsiento(AccionDeAcceso $accion): AsientoDeBitacora
{
    return AsientoDeBitacora::nuevo(
        operador: new Operador(
            id: IdDeOperador::desdeOid('oid-77'),
            nombre: 'Mónica Salvatierra',
            correo: 'monica@agropartners.com.bo',
        ),
        idDePersona: 'p-8f2b1c40',
        accion: $accion,
        direccionIp: '190.129.4.7',
        ocurrioEl: new DateTimeImmutable('2026-09-15T14:30:00+00:00'),
    );
}

it('copia el nombre del operador en el asiento', function () {
    $asiento = unAsiento(AccionDeAcceso::Concedio);

    expect($asiento->operador)->toBe('Mónica Salvatierra')
        ->and($asiento->idDeOperador->valor)->toBe('oid-77');
});

it('registra accion, persona, momento e ip', function () {
    $asiento = unAsiento(AccionDeAcceso::Revoco);

    expect($asiento->accion)->toBe(AccionDeAcceso::Revoco)
        ->and($asiento->idDePersona)->toBe('p-8f2b1c40')
        ->and($asiento->ocurrioEl->format('c'))->toBe('2026-09-15T14:30:00+00:00')
        ->and($asiento->direccionIp)->toBe('190.129.4.7');
});

it('cada asiento tiene un identificador propio', function () {
    expect(unAsiento(AccionDeAcceso::Concedio)->idDeAsiento)
        ->not->toBe(unAsiento(AccionDeAcceso::Concedio)->idDeAsiento);
});

it('cada accion sabe decirse en la pantalla', function () {
    expect(AccionDeAcceso::Concedio->value)->toBe('concedio')
        ->and(AccionDeAcceso::Revoco->value)->toBe('revoco')
        ->and(AccionDeAcceso::Concedio->comoTexto())->toBe('concedió el acceso')
        ->and(AccionDeAcceso::Revoco->comoTexto())->toBe('quitó el acceso');
});
