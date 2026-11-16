<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Identidad\Application\Contracts\ColaDePush;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Identidad\Infrastructure\Push\ColaDePushEnLaravel;
use Identidad\Infrastructure\Push\EnviarPushJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;
use Maestros\Domain\Contactos\IdDePersona;
use Ramsey\Uuid\Uuid;
use Tests\Dobles\ColaDePushEnMemoria;
use Tests\Soporte\RelojFijo;

uses(RefreshDatabase::class);

/** Monica y Lucía son contactos de C-004871; Javier, de C-004872. Los tres, del Grupo Monasterio. */
const MONICA_PUSH = 'n-59170741828';
const LUCIA_PUSH = 'n-59170223344';
const JAVIER_PUSH = 'n-59170334455';

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(RelojDelSistema::class, new RelojFijo('2026-11-16T15:00:00Z'));
    $this->cola = new ColaDePushEnMemoria;
    $this->app->instance(ColaDePush::class, $this->cola);
});

function sesionConToken(string $id, string $persona, ?string $token, string $vence = '2036-11-16T12:00:00Z', ?string $instalacion = null): SesionDeAplicacion
{
    $sesion = SesionDeAplicacion::abrir(
        IdDeSesion::desde($id),
        IdDePersona::desde($persona),
        Dispositivo::registrar(
            IdDeInstalacion::desde($instalacion ?? "inst-{$id}"),
            Plataforma::Android,
            $token === null ? null : TokenPush::desde($token),
        ),
        new DateTimeImmutable('2026-11-01T12:00:00Z'),
        new DateTimeImmutable($vence),
    );
    app(SesionRepository::class)->save($sesion);

    return $sesion;
}

/**
 * El ejemplo de ms-comercial, leído a las 14:00 del 16 y con el documento de
 * ese día: no es viejo para el reloj de las 15:00.
 *
 * @param  array<string, mixed>  $datos
 * @param  array<string, mixed>  $sobre
 */
function avisoDelBus(array $datos = [], array $sobre = []): Result
{
    $ejemplo = json_decode((string) file_get_contents(base_path('tests/Soporte/eventos/comercial-aviso-creado.json')), true);
    $ejemplo['eventoId'] = Uuid::uuid4()->toString();
    $ejemplo['ocurridoEn'] = '2026-11-16T14:00:00.000000Z';
    $ejemplo['datos'] = $datos + ['creadoEn' => '2026-11-16T14:00:00.000000Z', 'fechaDelDocumento' => '2026-11-16'] + $ejemplo['datos'];

    return app(Mediator::class)->send(new ProcesarEvento(Sobre::desdeArreglo(array_replace_recursive($ejemplo, $sobre))));
}

/** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
function avisoDeCatalogo(): array
{
    return [
        ['avisoId' => 'AV-C-A-0999', 'categoria' => 'catalogo', 'cardCode' => null, 'fechaDelDocumento' => null],
        ['clave' => ['avisoId' => 'AV-C-A-0999']],
    ];
}

it('encola un trabajo por cada sesion abierta con token de quien ve el socio', function () {
    sesionConToken('monica-telefono', MONICA_PUSH, 'fcm-1');
    sesionConToken('monica-tablet', MONICA_PUSH, 'fcm-2');

    $resultado = avisoDelBus();

    expect($resultado->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and($this->cola->sesiones())->toBe(['monica-tablet', 'monica-telefono'])
        ->and($this->cola->encolados[0]['aviso']->avisoId)->toBe('AV-F-1990');
});

it('quien no ve el socio no recibe', function () {
    sesionConToken('javier', JAVIER_PUSH, 'fcm-javier');
    sesionConToken('monica', MONICA_PUSH, 'fcm-monica');

    avisoDelBus();

    expect($this->cola->sesiones())->toBe(['monica']);
});

it('la categoria apagada no recibe', function () {
    sesionConToken('monica', MONICA_PUSH, 'fcm-monica');
    sesionConToken('lucia', LUCIA_PUSH, 'fcm-lucia');
    DB::table('identidad_preferencias_de_push')->insert([
        'id_de_persona' => LUCIA_PUSH, 'facturas' => false, 'pagos' => true, 'reportes' => true, 'catalogo' => true,
        'vigente_desde' => '2026-11-01 00:00:00.000000',
    ]);

    avisoDelBus();

    expect($this->cola->sesiones())->toBe(['monica']);
});

it('una sesion vencida, una cerrada y una sin token no reciben', function () {
    sesionConToken('vencida', MONICA_PUSH, 'fcm-1', vence: '2026-11-10T00:00:00Z');
    $cerrada = sesionConToken('cerrada', MONICA_PUSH, 'fcm-2');
    $cerrada->cerrar(new DateTimeImmutable('2026-11-15T00:00:00Z'));
    app(SesionRepository::class)->save($cerrada);
    sesionConToken('sin-token', MONICA_PUSH, null);
    sesionConToken('abierta', MONICA_PUSH, 'fcm-3');

    avisoDelBus();

    expect($this->cola->sesiones())->toBe(['abierta']);
});

it('una instalacion que paso a otra persona solo le llega a la nueva', function () {
    // El login de Lucía en el teléfono de Monica le quitó el dispositivo a la sesión de Monica (NP4).
    $deMonica = sesionConToken('monica', MONICA_PUSH, 'fcm-del-telefono', instalacion: 'telefono-compartido');
    $deMonica->desvincularDispositivo();
    app(SesionRepository::class)->save($deMonica);
    sesionConToken('lucia', LUCIA_PUSH, 'fcm-del-telefono', instalacion: 'telefono-compartido');

    avisoDelBus();

    expect($this->cola->sesiones())->toBe(['lucia']);
});

it('el catalogo le llega a toda persona que entra', function () {
    sesionConToken('monica', MONICA_PUSH, 'fcm-1');
    sesionConToken('javier', JAVIER_PUSH, 'fcm-2');

    avisoDelBus(...avisoDeCatalogo());

    expect($this->cola->sesiones())->toBe(['javier', 'monica']);
});

it('una persona sin contexto no recibe ni el catalogo', function () {
    // Una sesión abierta de alguien que hoy no entra: SAP lo dio de baja, o quedó en dos grupos.
    sesionConToken('de-nadie', 'n-59179999999', 'fcm-1');

    avisoDelBus(...avisoDeCatalogo());

    expect($this->cola->encolados)->toBe([]);
});

it('un token repetido recibe un solo trabajo', function () {
    sesionConToken('reinstalada', MONICA_PUSH, 'fcm-mismo');
    sesionConToken('vieja', MONICA_PUSH, 'fcm-mismo');

    avisoDelBus();

    expect($this->cola->encolados)->toHaveCount(1);
});

it('un aviso viejo queda aplicado y no encola nada', function (array $datos) {
    sesionConToken('monica', MONICA_PUSH, 'fcm-1');

    expect(avisoDelBus($datos)->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and($this->cola->encolados)->toBe([]);
})->with([
    'leido hace mas de 24 horas' => [['creadoEn' => '2026-11-15T14:00:00.000000Z']],
    'con el documento de anteayer' => [['fechaDelDocumento' => '2026-11-14']],
]);

it('un duplicado no encola de nuevo', function () {
    sesionConToken('monica', MONICA_PUSH, 'fcm-1');
    $mismoEvento = ['eventoId' => '3c9a7e21-5b4d-4f6a-8e2c-9d1b0a7f6e54'];

    avisoDelBus(sobre: $mismoEvento);
    $segundo = avisoDelBus(sobre: $mismoEvento);

    expect($segundo->value())->toBe(ResultadoDelEvento::Duplicado)
        ->and($this->cola->encolados)->toHaveCount(1);
});

it('un aviso mal formado es invalido y no encola nada', function (array $datos, array $sobre) {
    sesionConToken('monica', MONICA_PUSH, 'fcm-1');

    expect(fn () => avisoDelBus($datos, $sobre))->toThrow(EventoInvalido::class)
        ->and($this->cola->encolados)->toBe([])
        ->and(DB::table('maestros_evento_procesado')->count())->toBe(0);
})->with([
    'avisoId distinto en la clave' => [[], ['clave' => ['avisoId' => 'AV-F-1991']]],
    'categoria desconocida' => [['categoria' => 'promocion'], []],
    'titulo de 66 caracteres' => [['titulo' => str_repeat('a', 66)], []],
    'cuerpo vacio' => [['cuerpo' => ''], []],
    'fecha del documento mal escrita' => [['fechaDelDocumento' => '16/11/2026'], []],
    'creadoEn sin zona' => [['creadoEn' => '2026-11-16T14:00:00'], []],
    'avisoId de 61 caracteres' => [['avisoId' => str_repeat('A', 61)], ['clave' => ['avisoId' => str_repeat('A', 61)]]],
    // null es «para todos»: una factura con cardCode null sonaría en cada teléfono.
    'una factura con cardCode null' => [['cardCode' => null], []],
    'un aviso de catalogo con cardCode' => [['categoria' => 'catalogo', 'fechaDelDocumento' => null], []],
]);

it('un aviso al que le falta cardCode o fechaDelDocumento es invalido, no un aviso para todos', function (string $falta) {
    sesionConToken('monica', MONICA_PUSH, 'fcm-1');
    sesionConToken('javier', JAVIER_PUSH, 'fcm-2');

    $ejemplo = json_decode((string) file_get_contents(base_path('tests/Soporte/eventos/comercial-aviso-creado.json')), true);
    $ejemplo['datos']['creadoEn'] = '2026-11-16T14:00:00.000000Z';
    $ejemplo['datos']['fechaDelDocumento'] = '2026-11-16';
    unset($ejemplo['datos'][$falta]);

    expect(fn () => app(Mediator::class)->send(new ProcesarEvento(Sobre::desdeArreglo($ejemplo))))->toThrow(EventoInvalido::class)
        ->and($this->cola->encolados)->toBe([]);
})->with(['cardCode', 'fechaDelDocumento']);

it('el trabajo real va a la cola push y no lleva el token', function () {
    Queue::fake();
    $this->app->instance(ColaDePush::class, new ColaDePushEnLaravel);
    sesionConToken('monica', MONICA_PUSH, 'fcm-token-de-prueba');

    avisoDelBus();

    Queue::assertPushedOn('push', EnviarPushJob::class);
    Queue::assertPushed(EnviarPushJob::class, fn (EnviarPushJob $job): bool => ! str_contains(serialize($job), 'fcm-token-de-prueba'));
});
