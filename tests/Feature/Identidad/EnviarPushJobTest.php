<?php

declare(strict_types=1);

use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Contracts\ResultadoDeEnvio;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\CategoriaDeAviso;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Identidad\Infrastructure\Push\EnviarPushJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EnviadorDePushFalso;
use Tests\Soporte\RelojFijo;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->app->instance(RelojDelSistema::class, new RelojFijo('2026-11-16T15:00:00Z'));
    $this->sesion = SesionDeAplicacion::abrir(
        IdDeSesion::desde('s-monica'),
        IdDePersona::desde('n-59170741828'),
        Dispositivo::registrar(IdDeInstalacion::desde('inst-1'), Plataforma::Android, TokenPush::desde('fcm-al-encolar')),
        new DateTimeImmutable('2026-11-01T12:00:00Z'),
        new DateTimeImmutable('2036-11-16T12:00:00Z'),
    );
    app(SesionRepository::class)->save($this->sesion);
});

function trabajoDeFactura(string $creadoEn = '2026-11-16T14:00:00Z'): EnviarPushJob
{
    $aviso = new AvisoParaPush(
        'AV-F-1990',
        CategoriaDeAviso::Factura,
        'C-004871',
        'Factura N° 1990 disponible',
        'Ya puedes ver la factura de Sebastian Monasterio.',
        new DateTimeImmutable($creadoEn),
        '2026-11-16',
    );

    return (new EnviarPushJob('s-monica', $aviso->aArreglo()))->withFakeQueueInteractions();
}

function correrCon(EnviarPushJob $trabajo, ResultadoDeEnvio $respuesta): EnviadorDePushFalso
{
    $enviador = new EnviadorDePushFalso($respuesta);
    app()->instance(EnviadorDePush::class, $enviador);
    app()->call([$trabajo, 'handle']);

    return $enviador;
}

it('manda con el token que la sesion tiene al correr, no con el de cuando se encolo', function () {
    $trabajo = trabajoDeFactura();
    $this->sesion->cambiarTokenPush(TokenPush::desde('fcm-nuevo'), new DateTimeImmutable('2026-11-16T14:30:00Z'));
    app(SesionRepository::class)->save($this->sesion);

    $enviador = correrCon($trabajo, ResultadoDeEnvio::entregado());

    expect($enviador->enviados)->toBe([['token' => 'fcm-nuevo', 'avisoId' => 'AV-F-1990']]);
    $trabajo->assertNotReleased();
});

it('no manda si la sesion se cerro mientras esperaba', function () {
    $this->sesion->cerrar(new DateTimeImmutable('2026-11-16T14:30:00Z'));
    app(SesionRepository::class)->save($this->sesion);

    expect(correrCon(trabajoDeFactura(), ResultadoDeEnvio::entregado())->enviados)->toBe([]);
});

it('no manda si la sesion vencio mientras esperaba', function () {
    $this->app->instance(RelojDelSistema::class, new RelojFijo('2036-11-17T00:00:00Z'));

    expect(correrCon(trabajoDeFactura('2036-11-16T23:00:00Z'), ResultadoDeEnvio::entregado())->enviados)->toBe([]);
});

it('no manda si la persona apago la categoria mientras esperaba', function () {
    DB::table('identidad_preferencias_de_push')->insert([
        'id_de_persona' => 'n-59170741828', 'facturas' => false, 'pagos' => true, 'reportes' => true, 'catalogo' => true,
        'vigente_desde' => '2026-11-16 14:30:00.000000',
    ]);

    expect(correrCon(trabajoDeFactura(), ResultadoDeEnvio::entregado())->enviados)->toBe([]);
});

it('no manda un aviso que se volvio viejo mientras esperaba', function () {
    $this->app->instance(RelojDelSistema::class, new RelojFijo('2026-11-17T15:00:01Z'));

    expect(correrCon(trabajoDeFactura(), ResultadoDeEnvio::entregado())->enviados)->toBe([]);
});

it('un token muerto se borra de la sesion y no se reintenta', function () {
    $trabajo = trabajoDeFactura();

    correrCon($trabajo, ResultadoDeEnvio::tokenMuerto());

    expect(app(SesionRepository::class)->find(IdDeSesion::desde('s-monica'))?->tokenPush())->toBeNull();
    $trabajo->assertNotReleased();
    $trabajo->assertNotFailed();
});

it('un rechazo no se reintenta ni borra el token', function () {
    $trabajo = trabajoDeFactura();

    correrCon($trabajo, ResultadoDeEnvio::rechazado('SENDER_ID_MISMATCH'));

    expect(app(SesionRepository::class)->find(IdDeSesion::desde('s-monica'))?->tokenPush()?->valor())->toBe('fcm-al-encolar');
    $trabajo->assertNotReleased();
});

it('reintentarEn libera el trabajo con esa espera', function () {
    $trabajo = trabajoDeFactura();

    correrCon($trabajo, ResultadoDeEnvio::reintentarEn(120));

    $trabajo->assertReleased(delay: 120);
});

it('sin espera indicada, el primer reintento espera un minuto', function () {
    $trabajo = trabajoDeFactura();

    correrCon($trabajo, ResultadoDeEnvio::reintentarEn(null));

    $trabajo->assertReleased(delay: 60);
});

it('la espera crece con cada intento y no pasa de una hora', function () {
    expect(EnviarPushJob::espera(1))->toBe(60)
        ->and(EnviarPushJob::espera(2))->toBe(120)
        ->and(EnviarPushJob::espera(5))->toBe(960)
        ->and(EnviarPushJob::espera(10))->toBe(3600);
});
