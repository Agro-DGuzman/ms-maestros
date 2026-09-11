<?php

declare(strict_types=1);

use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

it('guarda una sesion con su dispositivo y la recupera', function () {
    $repo = app(SesionRepository::class);

    $repo->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'),
        IdDePersona::desde('p-8f2b1c40'),
        Dispositivo::registrar(IdDeInstalacion::desde('inst-1'), Plataforma::Ios),
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
        new DateTimeImmutable('2026-10-11T12:00:00Z'),
    ));

    $sesion = $repo->find(IdDeSesion::desde('s-1'));

    expect($sesion->dispositivo()->plataforma())->toBe(Plataforma::Ios)
        ->and($sesion->persona()->value())->toBe('p-8f2b1c40');
});

it('lista solo las sesiones abiertas de la persona', function () {
    $repo = app(SesionRepository::class);
    $persona = IdDePersona::desde('p-8f2b1c40');

    $abierta = SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-1'), $persona, null,
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
        new DateTimeImmutable('2036-10-11T12:00:00Z'),
    );

    $cerrada = SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-2'), $persona, null,
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
        new DateTimeImmutable('2036-10-11T12:00:00Z'),
    );
    $cerrada->cerrar(new DateTimeImmutable('2026-09-11T13:00:00Z'));

    $repo->save($abierta);
    $repo->save($cerrada);

    expect($repo->abiertasDe($persona))->toHaveCount(1);
});
