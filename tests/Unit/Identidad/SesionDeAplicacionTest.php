<?php

declare(strict_types=1);

use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Maestros\Domain\Contactos\IdDePersona;

function unaSesion(): SesionDeAplicacion
{
    return SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        IdDePersona::desde('p-8f2b1c40'),
        Dispositivo::registrar(IdDeInstalacion::desde('inst-1'), Plataforma::Android),
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
        new DateTimeImmutable('2026-10-11T12:00:00Z'),
    );
}

it('nace abierta y atada a su dispositivo', function () {
    $sesion = unaSesion();

    expect($sesion->estaAbierta(new DateTimeImmutable('2026-09-12T00:00:00Z')))->toBeTrue()
        ->and($sesion->persona()->value())->toBe('p-8f2b1c40')
        ->and($sesion->dispositivo()->plataforma())->toBe(Plataforma::Android);
});

it('se cierra y ya no vuelve a abrirse', function () {
    $sesion = unaSesion();
    $sesion->cerrar(new DateTimeImmutable('2026-09-12T00:00:00Z'));

    expect($sesion->estaAbierta(new DateTimeImmutable('2026-09-12T00:01:00Z')))->toBeFalse();
});

it('deja de estar abierta al vencer', function () {
    expect(unaSesion()->estaAbierta(new DateTimeImmutable('2026-10-11T12:00:01Z')))->toBeFalse();
});

it('cerrar dos veces conserva la primera marca', function () {
    $sesion = unaSesion();
    $primera = new DateTimeImmutable('2026-09-12T00:00:00Z');
    $sesion->cerrar($primera);
    $sesion->cerrar(new DateTimeImmutable('2026-09-13T00:00:00Z'));

    expect($sesion->cerradaEn()->format('c'))->toBe($primera->format('c'));
});

it('la plataforma solo acepta los valores del contrato', function () {
    expect(Plataforma::from('android'))->toBe(Plataforma::Android)
        ->and(Plataforma::from('ios'))->toBe(Plataforma::Ios)
        ->and(Plataforma::tryFrom('windows'))->toBeNull();
});
