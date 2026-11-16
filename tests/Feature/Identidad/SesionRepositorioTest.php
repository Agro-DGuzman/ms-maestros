<?php

declare(strict_types=1);

use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Dispositivos\TokenPush;
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

/** Una sesión que vence en 2036, con dispositivo y, si se da, token push. */
function sesionGuardada(string $id, string $persona, ?string $instalacion = 'inst-1', ?string $token = null): SesionDeAplicacion
{
    $sesion = SesionDeAplicacion::abrir(
        IdDeSesion::desde($id),
        IdDePersona::desde($persona),
        $instalacion === null ? null : Dispositivo::registrar(
            IdDeInstalacion::desde($instalacion),
            Plataforma::Android,
            $token === null ? null : TokenPush::desde($token),
        ),
        new DateTimeImmutable('2026-11-16T12:00:00Z'),
        new DateTimeImmutable('2036-11-16T12:00:00Z'),
    );
    app(SesionRepository::class)->save($sesion);

    return $sesion;
}

it('guarda el token push con su fecha y los recupera', function () {
    $repo = app(SesionRepository::class);
    $sesion = sesionGuardada('s-1', 'n-59170741828');
    $sesion->cambiarTokenPush(TokenPush::desde('fcm-token-de-prueba'), new DateTimeImmutable('2026-11-16T13:00:00Z'));
    $repo->save($sesion);

    $leida = $repo->find(IdDeSesion::desde('s-1'));

    expect($leida?->tokenPush()?->valor())->toBe('fcm-token-de-prueba')
        ->and($leida?->tokenPushActualizadoEn()?->format(DATE_ATOM))->toBe('2026-11-16T13:00:00+00:00');
});

it('abiertasConTokenPush deja afuera una cerrada, una vencida y una sin token', function () {
    $repo = app(SesionRepository::class);
    $ahora = new DateTimeImmutable('2026-11-17T00:00:00Z');

    sesionGuardada('con-token', 'n-59170741828', 'inst-1', 'fcm-1');
    sesionGuardada('sin-token', 'n-59170741828', 'inst-2');
    $cerrada = sesionGuardada('cerrada', 'n-59170741828', 'inst-3', 'fcm-3');
    $cerrada->cerrar($ahora);
    $repo->save($cerrada);
    $repo->save(SesionDeAplicacion::abrir(
        IdDeSesion::desde('vencida'),
        IdDePersona::desde('n-59170741828'),
        Dispositivo::registrar(IdDeInstalacion::desde('inst-4'), Plataforma::Ios, TokenPush::desde('fcm-4')),
        new DateTimeImmutable('2026-10-01T00:00:00Z'),
        new DateTimeImmutable('2026-11-01T00:00:00Z'),
    ));

    $ids = array_map(fn (SesionDeAplicacion $s): string => $s->idDeSesion()->value(), $repo->abiertasConTokenPush($ahora));

    expect($ids)->toBe(['con-token']);
});

it('abiertasConInstalacion trae las de cualquier persona con esa instalacion', function () {
    sesionGuardada('de-una', 'n-59170741828', 'telefono-compartido');
    sesionGuardada('de-otra', 'n-59171112233', 'telefono-compartido');
    sesionGuardada('otro-telefono', 'n-59170741828', 'otro');

    $ids = array_map(
        fn (SesionDeAplicacion $s): string => $s->idDeSesion()->value(),
        app(SesionRepository::class)->abiertasConInstalacion(IdDeInstalacion::desde('telefono-compartido'), new DateTimeImmutable('2026-11-17T00:00:00Z')),
    );
    sort($ids);

    expect($ids)->toBe(['de-otra', 'de-una']);
});
