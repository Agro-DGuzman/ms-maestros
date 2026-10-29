<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\Contrato;
use Tests\Soporte\PropiedadesDeEjemplo;
use Tests\Soporte\ReplicaDeEjemplo;
use Tests\Soporte\SobresDePropiedad;

uses(RefreshDatabase::class);

/** @param array<string, mixed> $sobre */
function procesarSobre(array $sobre): Result
{
    return app(Mediator::class)->send(new ProcesarEvento(Sobre::desdeArreglo($sobre)));
}

function propiedadGuardada(string $id = 'PROP-014'): ?object
{
    return DB::table('maestros_propiedad')->where('id_propiedad', $id)->first();
}

function eventosProcesados(): int
{
    return DB::table('maestros_evento_procesado')->count();
}

it('el ejemplo del contrato crea la propiedad activa con su vigencia', function () {
    expect(Contrato::diferenciasDeEsquema(SobresDePropiedad::de(), 'eventos-de-dominio-v1.yaml', 'PropiedadActualizada'))->toBe([]);

    $resultado = procesarSobre(SobresDePropiedad::de());

    expect($resultado->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and(propiedadGuardada()?->nombre)->toBe('Lote 14 · San Julián')
        ->and(propiedadGuardada()?->codigo_de_socio)->toBe('C-004871')
        ->and(propiedadGuardada()?->estado)->toBe('activo')
        ->and((string) propiedadGuardada()?->vigente_desde)->toStartWith('2026-10-21 12:00:00.000000')
        ->and(eventosProcesados())->toBe(1);
});

it('una foto mas nueva la reemplaza y la puede mover de socio', function () {
    procesarSobre(SobresDePropiedad::de());

    $resultado = procesarSobre(SobresDePropiedad::otra(
        ['nombre' => 'Lote 14 · San Julián Norte', 'cardCode' => 'C-004872', 'estado' => 'inactivo'],
        ['ocurridoEn' => '2026-10-22T09:00:00Z'],
    ));

    expect($resultado->value())->toBe(ResultadoDelEvento::Aplicado)
        ->and(propiedadGuardada()?->nombre)->toBe('Lote 14 · San Julián Norte')
        ->and(propiedadGuardada()?->codigo_de_socio)->toBe('C-004872')
        ->and(propiedadGuardada()?->estado)->toBe('inactivo');
});

it('una foto igual o mas vieja se ignora y queda registrada', function (string $ocurridoEn) {
    procesarSobre(SobresDePropiedad::de());

    $resultado = procesarSobre(SobresDePropiedad::otra(['nombre' => 'Vieja'], ['ocurridoEn' => $ocurridoEn]));

    expect($resultado->value())->toBe(ResultadoDelEvento::IgnoradoPorViejo)
        ->and(propiedadGuardada()?->nombre)->toBe('Lote 14 · San Julián')
        ->and(eventosProcesados())->toBe(2);
})->with(['igual' => ['2026-10-21T12:00:00Z'], 'mas vieja' => ['2026-10-20T12:00:00Z']]);

it('dentro del mismo segundo gana la de mas microsegundos', function () {
    procesarSobre(SobresDePropiedad::de(sobre: ['ocurridoEn' => '2026-10-21T12:00:00.100Z']));
    procesarSobre(SobresDePropiedad::otra(['nombre' => 'La de las 12:00:00.900'], ['ocurridoEn' => '2026-10-21T12:00:00.900Z']));
    procesarSobre(SobresDePropiedad::otra(['nombre' => 'La de las 12:00:00.500'], ['ocurridoEn' => '2026-10-21T12:00:00.500Z']));

    expect(propiedadGuardada()?->nombre)->toBe('La de las 12:00:00.900');
});

it('un ocurridoEn con desfase se compara en UTC', function () {
    procesarSobre(SobresDePropiedad::de());

    // 08:00 en -04:00 son las 12:00 UTC: igual a lo guardado, no más nueva.
    $resultado = procesarSobre(SobresDePropiedad::otra(['nombre' => 'Con desfase'], ['ocurridoEn' => '2026-10-21T08:00:00-04:00']));

    expect($resultado->value())->toBe(ResultadoDelEvento::IgnoradoPorViejo)
        ->and(propiedadGuardada()?->nombre)->toBe('Lote 14 · San Julián');
});

it('una de la semilla, sin vigencia, se reemplaza aunque la foto sea de 1970', function () {
    PropiedadesDeEjemplo::sembrar();

    procesarSobre(SobresDePropiedad::de(['nombre' => 'Lote 14 real', 'cardCode' => '10057'], ['ocurridoEn' => '1970-01-01T00:00:00.000000Z']));

    expect(propiedadGuardada()?->nombre)->toBe('Lote 14 real')
        ->and(propiedadGuardada()?->codigo_de_socio)->toBe('10057');
});

it('la de un ITR llega con 1970 y cualquier foto real la pisa', function () {
    procesarSobre(SobresDePropiedad::de(sobre: ['ocurridoEn' => '1970-01-01T00:00:00.000000Z']));
    procesarSobre(SobresDePropiedad::otra(['nombre' => 'Desde Appgronomy'], ['ocurridoEn' => '2026-10-21T12:00:00Z']));

    expect(propiedadGuardada()?->nombre)->toBe('Desde Appgronomy');
});

it('un eventoId repetido es Duplicado y no cambia nada', function () {
    procesarSobre(SobresDePropiedad::de());

    $resultado = procesarSobre(SobresDePropiedad::de(['nombre' => 'Otro'], ['ocurridoEn' => '2026-10-22T12:00:00Z']));

    expect($resultado->value())->toBe(ResultadoDelEvento::Duplicado)
        ->and(propiedadGuardada()?->nombre)->toBe('Lote 14 · San Julián')
        ->and(eventosProcesados())->toBe(1);
});

it('un dato invalido lanza EventoInvalido y no deja nada escrito', function (array $sobre) {
    expect(fn () => procesarSobre($sobre))->toThrow(EventoInvalido::class)
        ->and(propiedadGuardada())->toBeNull()
        ->and(eventosProcesados())->toBe(0);
})->with([
    'estado que no es del contrato' => [SobresDePropiedad::de(['estado' => 'baja'])],
    'sin estado' => [(static function (): array {
        $sobre = SobresDePropiedad::de();
        unset($sobre['datos']['estado']);

        return $sobre;
    })()],
    'nombre con espacio al final' => [SobresDePropiedad::de(['nombre' => 'Lote 14 '])],
    'propiedadId distinto en la clave' => [SobresDePropiedad::de(sobre: ['clave' => ['objeto' => 'propiedad', 'propiedadId' => 'PROP-007']])],
    'tipo desconocido' => [SobresDePropiedad::de(sobre: ['tipo' => 'comercial.otra.cosa'])],
]);

it('una inactiva por evento sale de la lista de la App', function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso(ReplicaDeEjemplo::MONICA));
    PropiedadesDeEjemplo::sembrar();

    procesarSobre(SobresDePropiedad::de(['estado' => 'inactivo']));

    $respuesta = $this->getJson('/v1/socios/C-004871/propiedades', ['Authorization' => 'Bearer token-bueno'])->assertStatus(200);

    expect($respuesta->json('data.items'))->toBe([['id' => 'PROP-007', 'nombre' => 'Lote 07 · Cuatro Cañadas']]);
});
