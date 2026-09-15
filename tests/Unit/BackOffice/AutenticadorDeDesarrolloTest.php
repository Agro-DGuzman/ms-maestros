<?php

declare(strict_types=1);

use BackOffice\Application\Contracts\IngresoRechazado;
use BackOffice\Infrastructure\Entra\AutenticadorDeDesarrollo;

function autenticadorDeDesarrollo(string $entorno = 'local'): AutenticadorDeDesarrollo
{
    return new AutenticadorDeDesarrollo(
        entorno: $entorno,
        nombre: 'Operador Local',
        correo: 'local@agropartners.com.bo',
        oid: 'dev-0001',
        urlDeCallback: 'http://localhost/admin/callback',
    );
}

it('se niega a construirse en produccion', function () {
    expect(fn () => autenticadorDeDesarrollo('production'))
        ->toThrow(RuntimeException::class, 'AUTENTICADOR_DE_DESARROLLO_EN_PRODUCCION');
});

it('la url de ingreso vuelve al callback con el estado', function () {
    $url = autenticadorDeDesarrollo()->urlDeIngreso('estado-123', 'desafio-abc');

    expect($url)->toContain('http://localhost/admin/callback')
        ->and($url)->toContain('state=estado-123')
        ->and($url)->toContain('code=desarrollo');
});

it('resuelve el operador de configuracion', function () {
    $operador = autenticadorDeDesarrollo()->resolver('desarrollo', 'desafio-abc');

    expect($operador->id->valor)->toBe('dev-0001')
        ->and($operador->nombre)->toBe('Operador Local')
        ->and($operador->correo)->toBe('local@agropartners.com.bo');
});

it('rechaza un codigo que no emitio', function () {
    expect(fn () => autenticadorDeDesarrollo()->resolver('otro-codigo', 'desafio-abc'))
        ->toThrow(IngresoRechazado::class);
});

it('el rechazo lleva el codigo que la pantalla necesita distinguir', function () {
    expect(IngresoRechazado::codigoInvalido()->codigo)->toBe('CODIGO_INVALIDO')
        ->and(IngresoRechazado::sinRolAdministrador()->codigo)->toBe('SIN_ROL_ADMINISTRADOR')
        ->and(IngresoRechazado::identidadNoDisponible()->codigo)->toBe('IDENTIDAD_NO_DISPONIBLE');
});
