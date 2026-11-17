<?php

declare(strict_types=1);

use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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

it('guarda el token nuevo de una sesion que sigue abierta', function () {
    $repo = app(SesionRepository::class);
    $sesion = sesionGuardada('s-1', 'n-59170741828', 'inst-1');
    $sesion->cambiarTokenPush(TokenPush::desde('fcm-nuevo'), new DateTimeImmutable('2026-11-17T12:00:00Z'));

    expect($repo->guardarTokenPushSiSigueAbierta($sesion, new DateTimeImmutable('2026-11-17T12:00:00Z')))->toBeTrue()
        ->and($repo->find($sesion->idDeSesion())?->tokenPush()?->valor())->toBe('fcm-nuevo');
});

it('no reabre una sesion que se cerro despues de leerla', function () {
    $repo = app(SesionRepository::class);
    sesionGuardada('s-1', 'n-59170741828', 'inst-1');
    $leida = $repo->find(IdDeSesion::desde('s-1'));

    $cerrada = $repo->find(IdDeSesion::desde('s-1'));
    $cerrada?->cerrar(new DateTimeImmutable('2026-11-17T12:00:00Z'));
    $repo->save($cerrada);

    $leida?->cambiarTokenPush(TokenPush::desde('fcm-nuevo'), new DateTimeImmutable('2026-11-17T12:00:01Z'));

    expect($repo->guardarTokenPushSiSigueAbierta($leida, new DateTimeImmutable('2026-11-17T12:00:01Z')))->toBeFalse()
        ->and($repo->find(IdDeSesion::desde('s-1'))?->cerradaEn())->not->toBeNull()
        ->and($repo->find(IdDeSesion::desde('s-1'))?->tokenPush())->toBeNull();
});

it('no guarda el token si el telefono paso a otra sesion despues de leerla', function () {
    $repo = app(SesionRepository::class);
    sesionGuardada('s-1', 'n-59170741828', 'inst-1');
    $leida = $repo->find(IdDeSesion::desde('s-1'));

    $desvinculada = $repo->find(IdDeSesion::desde('s-1'));
    $desvinculada?->desvincularDispositivo();
    $repo->save($desvinculada);

    $leida?->cambiarTokenPush(TokenPush::desde('fcm-nuevo'), new DateTimeImmutable('2026-11-17T12:00:00Z'));

    expect($repo->guardarTokenPushSiSigueAbierta($leida, new DateTimeImmutable('2026-11-17T12:00:00Z')))->toBeFalse()
        ->and($repo->find(IdDeSesion::desde('s-1'))?->dispositivo())->toBeNull();
});

it('quitarEsteTokenPushDeOtras se lo quita a las de cualquier persona, menos a la indicada', function () {
    $repo = app(SesionRepository::class);
    sesionGuardada('mia', 'n-59170741828', 'inst-1', 'fcm-repetido');
    sesionGuardada('de-otra', 'n-59171112233', 'inst-2', 'fcm-repetido');
    sesionGuardada('con-otro', 'n-59171112233', 'inst-3', 'fcm-distinto');

    $repo->quitarEsteTokenPushDeOtras(TokenPush::desde('fcm-repetido'), IdDeSesion::desde('mia'));

    expect($repo->find(IdDeSesion::desde('mia'))?->tokenPush()?->valor())->toBe('fcm-repetido')
        ->and($repo->find(IdDeSesion::desde('de-otra'))?->tokenPush())->toBeNull()
        ->and($repo->find(IdDeSesion::desde('de-otra'))?->cerradaEn())->toBeNull()
        ->and($repo->find(IdDeSesion::desde('con-otro'))?->tokenPush()?->valor())->toBe('fcm-distinto');
});

it('quitarTokenPushSiSigueSiendo no borra un token que ya cambio', function () {
    $repo = app(SesionRepository::class);
    sesionGuardada('s-1', 'n-59170741828', 'inst-1', 'fcm-rotado');

    $repo->quitarTokenPushSiSigueSiendo(IdDeSesion::desde('s-1'), TokenPush::desde('fcm-viejo'));
    expect($repo->find(IdDeSesion::desde('s-1'))?->tokenPush()?->valor())->toBe('fcm-rotado');

    $repo->quitarTokenPushSiSigueSiendo(IdDeSesion::desde('s-1'), TokenPush::desde('fcm-rotado'));
    expect($repo->find(IdDeSesion::desde('s-1'))?->tokenPush())->toBeNull();
});

it('si la base falla en una consulta con el token, el token no queda en el mensaje del error', function (Closure $consulta) {
    // Toda consulta que lleva el token entre sus valores, no solo las que
    // guardan: Laravel arma el mensaje con ellos y ese mensaje va al log (NP16).
    Schema::rename('identidad_sesiones', 'identidad_sesiones_fuera');

    try {
        $consulta(app(SesionRepository::class), TokenPush::desde('fcm-token-de-prueba'));
        $mensaje = 'no lanzó';
    } catch (Throwable $e) {
        $mensaje = $e->getMessage();
    }

    expect($mensaje)->not->toBe('no lanzó')
        ->and($mensaje)->not->toContain('fcm-token-de-prueba');
})->with([
    'abiertasConEsteTokenPush' => [fn (SesionRepository $r, TokenPush $t) => $r->abiertasConEsteTokenPush($t, new DateTimeImmutable)],
    'quitarEsteTokenPushDeOtras' => [fn (SesionRepository $r, TokenPush $t) => $r->quitarEsteTokenPushDeOtras($t, IdDeSesion::desde('s-1'))],
    'quitarTokenPushSiSigueSiendo' => [fn (SesionRepository $r, TokenPush $t) => $r->quitarTokenPushSiSigueSiendo(IdDeSesion::desde('s-1'), $t)],
    'guardarTokenPushSiSigueAbierta' => [function (SesionRepository $r, TokenPush $t) {
        $sesion = SesionDeAplicacion::abrir(
            IdDeSesion::desde('s-1'),
            IdDePersona::desde('n-59170741828'),
            Dispositivo::registrar(IdDeInstalacion::desde('inst-1'), Plataforma::Android, $t),
            new DateTimeImmutable('2026-11-16T12:00:00Z'),
            new DateTimeImmutable('2036-11-16T12:00:00Z'),
        );

        return $r->guardarTokenPushSiSigueAbierta($sesion, new DateTimeImmutable);
    }],
]);

it('si la base falla al guardar, el token push no queda en el mensaje del error', function () {
    // Laravel arma el mensaje de una QueryException con los valores del INSERT,
    // y ese mensaje va al log (NP16).
    // Sin una columna, la consulta previa anda y el que falla es el INSERT, con el token entre sus valores.
    Schema::table('identidad_sesiones', fn (Blueprint $t) => $t->dropColumn('plataforma'));

    try {
        sesionGuardada('s-1', 'n-59170741828', 'inst-1', 'fcm-token-de-prueba');
        $mensaje = 'no lanzó';
    } catch (Throwable $e) {
        $mensaje = $e->getMessage();
    }

    expect($mensaje)->not->toBe('no lanzó')
        ->and($mensaje)->not->toContain('fcm-token-de-prueba');
});
