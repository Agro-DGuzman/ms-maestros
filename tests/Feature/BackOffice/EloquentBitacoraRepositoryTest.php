<?php

declare(strict_types=1);

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function asientoDe(string $idDePersona, AccionDeAcceso $accion, string $momento): AsientoDeBitacora
{
    return AsientoDeBitacora::nuevo(
        operador: new Operador(
            IdDeOperador::desdeOid('oid-77'),
            'Mónica Salvatierra',
            'monica@agropartners.com.bo',
        ),
        idDePersona: $idDePersona,
        accion: $accion,
        direccionIp: '190.129.4.7',
        ocurrioEl: new DateTimeImmutable($momento),
    );
}

it('devuelve el historial de una persona del mas reciente al mas viejo', function () {
    $repo = app(BitacoraRepository::class);

    $repo->asentar(asientoDe('p-1', AccionDeAcceso::Concedio, '2026-09-10T10:00:00+00:00'));
    $repo->asentar(asientoDe('p-1', AccionDeAcceso::Revoco, '2026-09-12T10:00:00+00:00'));
    $repo->asentar(asientoDe('p-2', AccionDeAcceso::Concedio, '2026-09-13T10:00:00+00:00'));

    $historial = $repo->historialDe('p-1');

    expect($historial)->toHaveCount(2)
        ->and($historial[0]->accion)->toBe(AccionDeAcceso::Revoco)
        ->and($historial[1]->accion)->toBe(AccionDeAcceso::Concedio);
});

it('conserva el nombre del operador tal como se asento', function () {
    $repo = app(BitacoraRepository::class);
    $repo->asentar(asientoDe('p-3', AccionDeAcceso::Concedio, '2026-09-10T10:00:00+00:00'));

    expect($repo->historialDe('p-3')[0]->operador)->toBe('Mónica Salvatierra');
});

it('una persona sin movimientos devuelve una lista vacia', function () {
    expect(app(BitacoraRepository::class)->historialDe('p-inexistente'))->toBe([]);
});

it('el repositorio no expone forma de modificar ni borrar', function () {
    $metodos = array_map(
        static fn (ReflectionMethod $m): string => $m->name,
        (new ReflectionClass(BitacoraRepository::class))->getMethods(),
    );

    expect($metodos)->toBe(['asentar', 'historialDe']);
});
