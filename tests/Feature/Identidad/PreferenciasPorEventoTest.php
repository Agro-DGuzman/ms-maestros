<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Identidad\Domain\Push\CategoriaDeAviso;
use Identidad\Domain\Push\PreferenciasDePush;
use Identidad\Domain\Push\PreferenciasDePushRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;
use Maestros\Domain\Contactos\IdDePersona;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

/**
 * El ejemplo de ms-comercial, con lo que se quiera cambiar. Cada llamada es
 * un evento distinto, salvo que se pase el mismo eventoId.
 *
 * @param  array<string, mixed>  $datos
 * @param  array<string, mixed>  $sobre
 */
function preferenciasDelBus(array $datos = [], array $sobre = []): Result
{
    $ejemplo = json_decode((string) file_get_contents(base_path('tests/Soporte/eventos/comercial-preferencias-actualizadas.json')), true);
    $ejemplo['eventoId'] = Uuid::uuid4()->toString();
    $ejemplo['datos'] = $datos + $ejemplo['datos'];

    return app(Mediator::class)->send(new ProcesarEvento(Sobre::desdeArreglo(array_replace_recursive($ejemplo, $sobre))));
}

function preferenciasDe(string $persona): PreferenciasDePush
{
    return app(PreferenciasDePushRepository::class)->de(IdDePersona::desde($persona));
}

it('guarda las preferencias que llegan por el bus', function () {
    $resultado = preferenciasDelBus();

    expect($resultado->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and(preferenciasDe('n-59170000000')->permite(CategoriaDeAviso::Pago))->toBeFalse()
        ->and(preferenciasDe('n-59170000000')->permite(CategoriaDeAviso::Factura))->toBeTrue()
        ->and(DB::table('maestros_evento_procesado')->where('tipo', 'comercial.preferencias.actualizadas')->count())->toBe(1);
});

it('una foto vieja no pisa una nueva', function () {
    preferenciasDelBus(['pagos' => false], ['ocurridoEn' => '2026-10-11T18:00:00.000000Z']);

    $resultado = preferenciasDelBus(['pagos' => true], ['ocurridoEn' => '2026-10-10T18:00:00.000000Z']);

    expect($resultado->value())->toBe(ResultadoDelEvento::IgnoradoPorViejo)
        ->and(preferenciasDe('n-59170000000')->permite(CategoriaDeAviso::Pago))->toBeFalse();
});

it('una foto mas nueva reemplaza a la anterior', function () {
    preferenciasDelBus(['pagos' => false], ['ocurridoEn' => '2026-10-11T18:00:00.000000Z']);
    preferenciasDelBus(['pagos' => true], ['ocurridoEn' => '2026-10-11T18:00:00.500000Z']);

    expect(preferenciasDe('n-59170000000')->permite(CategoriaDeAviso::Pago))->toBeTrue();
});

it('las de una persona sin sesiones ni contactos se guardan igual', function () {
    // ms-comercial publica una vez todas las existentes: muchas son de gente sin sesión abierta.
    expect(DB::table('identidad_sesiones')->count())->toBe(0)
        ->and(preferenciasDelBus()->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and(preferenciasDe('n-59170000000')->vigenteDesde())->not->toBeNull();
});

it('un evento mal formado es invalido y no deja nada', function (array $datos, array $sobre) {
    expect(fn () => preferenciasDelBus($datos, $sobre))->toThrow(EventoInvalido::class)
        ->and(DB::table('maestros_evento_procesado')->count())->toBe(0)
        ->and(DB::table('identidad_preferencias_de_push')->count())->toBe(0);
})->with([
    'clave.persona distinta de datos.persona' => [[], ['clave' => ['persona' => 'n-59171112233']]],
    'un interruptor que no es booleano' => [['facturas' => 'si'], []],
    'una persona vacia' => [['persona' => ''], ['clave' => ['persona' => '']]],
    'una persona de mas de 100 caracteres' => [['persona' => str_repeat('n', 101)], ['clave' => ['persona' => str_repeat('n', 101)]]],
]);
