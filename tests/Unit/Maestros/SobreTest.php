<?php

declare(strict_types=1);

use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Tests\Soporte\SobresDePropiedad;

it('lee el sobre de la spec', function () {
    $sobre = Sobre::desdeArreglo(SobresDePropiedad::de());

    expect($sobre->eventoId)->toBe('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d')
        ->and($sobre->tipo)->toBe('comercial.propiedad.actualizada')
        ->and($sobre->origen)->toBe('ms-comercial')
        ->and($sobre->version)->toBe(1)
        ->and($sobre->clave['propiedadId'])->toBe('PROP-014')
        ->and($sobre->datos['nombre'])->toBe('Lote 14 · San Julián')
        ->and($sobre->ocurridoEn->format('Y-m-d\TH:i:s.vP'))->toBe('2026-10-21T12:00:00.000+00:00');
});

it('pasa a UTC un desfase', function () {
    $sobre = Sobre::desdeArreglo(SobresDePropiedad::de(sobre: ['ocurridoEn' => '2026-10-21T08:00:00.431-04:00']));

    expect($sobre->ocurridoEn->format('H:i:s.v P'))->toBe('12:00:00.431 +00:00');
});

it('acepta segundos sin fraccion y conserva seis decimales', function () {
    expect(Sobre::desdeArreglo(SobresDePropiedad::de(sobre: ['ocurridoEn' => '2026-10-21T12:00:00Z']))->ocurridoEn->format('Y-m-d\TH:i:s.uP'))
        ->toBe('2026-10-21T12:00:00.000000+00:00')
        ->and(Sobre::desdeArreglo(SobresDePropiedad::de(sobre: ['ocurridoEn' => '2026-10-21T12:00:00.123456Z']))->ocurridoEn->format('u'))
        ->toBe('123456');
});

it('el eventoId se guarda en minusculas', function () {
    // Es la clave de idempotencia: el mismo UUID con otras mayúsculas no
    // puede pasar por un evento distinto.
    $sobre = Sobre::desdeArreglo(SobresDePropiedad::de(sobre: ['eventoId' => 'A1B2C3D4-E5F6-4A7B-8C9D-0E1F2A3B4C5D']));

    expect($sobre->eventoId)->toBe('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d');
});

it('rechaza un sobre mal formado', fn (array $sobre) => Sobre::desdeArreglo(SobresDePropiedad::de(sobre: $sobre)))
    ->throws(EventoInvalido::class)
    ->with([
        'sin zona' => [['ocurridoEn' => '2026-10-21T12:00:00']],
        'fecha sin hora' => [['ocurridoEn' => '2026-10-21']],
        'siete decimales' => [['ocurridoEn' => '2026-10-21T12:00:00.1234567Z']],
        'version 2' => [['version' => 2]],
        'version string' => [['version' => '1']],
        'eventoId' => [['eventoId' => 'no-es-uuid']],
        'eventoId con salto de linea' => [['eventoId' => "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d\n"]],
        'ocurridoEn con salto de linea' => [['ocurridoEn' => "2026-10-21T12:00:00.000Z\n"]],
        'tipo vacio' => [['tipo' => '']],
        'sin datos' => [['datos' => null]],
        'datos no es objeto' => [['datos' => 'texto']],
        'clave no es objeto' => [['clave' => 5]],
    ]);

it('rechaza un cuerpo que no es JSON', fn () => Sobre::desdeJson('{'))->throws(EventoInvalido::class);

it('lee el mismo sobre desde JSON', function () {
    $sobre = Sobre::desdeJson((string) json_encode(SobresDePropiedad::de()));

    expect($sobre->eventoId)->toBe('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d')
        ->and($sobre->datos['zona'])->toBe('norte');
});
