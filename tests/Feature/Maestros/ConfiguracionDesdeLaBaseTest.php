<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maestros\Domain\Aplicacion\EstadoDeVersion;
use Maestros\Domain\Aplicacion\Version;
use Maestros\Infrastructure\Configuracion\ConfiguracionDesdeLaBase;
use Psr\Log\NullLogger;
use Tests\Dobles\LoggerEspia;
use Tests\Soporte\ConfiguracionDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    ConfiguracionDeEjemplo::sembrar();
});

function adaptador(): ConfiguracionDesdeLaBase
{
    return new ConfiguracionDesdeLaBase(new NullLogger);
}

it('lee las cuentas en el orden de la columna orden, no en el de insercion', function () {
    ConfiguracionDeEjemplo::cuentas([
        ['banco' => 'Banco BISA', 'cuenta' => '701-5032719-3-81', 'orden' => 2],
        ['banco' => 'Banco Nacional de Bolivia', 'cuenta' => '10000006816033', 'orden' => 1],
    ]);
    $cuentas = adaptador()->cuentasParaPagar()->value();

    expect($cuentas->cuentas[0]->banco)->toBe('Banco Nacional de Bolivia')
        ->and($cuentas->cuentas[1]->numeroCuenta)->toBe('701-5032719-3-81')
        ->and($cuentas->nitDelTitular)->toBe('1013879029');
});

// Sin closures en el dataset: Pest las resuelve antes de pasarlas y la prueba
// recibiría su resultado, no la acción.
it('un valor que falta o esta mal cargado es un problema nuestro', function (string $clave, ?string $valor, string $metodo, array $argumentos) {
    $valor === null ? ConfiguracionDeEjemplo::quitar($clave) : ConfiguracionDeEjemplo::poner($clave, $valor);
    $resultado = adaptador()->{$metodo}(...$argumentos);

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
})->with([
    'sin NIT' => ['titular.nit', null, 'cuentasParaPagar', []],
    'un NIT de espacios' => ['titular.nit', '   ', 'cuentasParaPagar', []],
    'sin telefono' => ['atencion.telefono', null, 'canalDeAtencion', []],
    'un telefono fijo' => ['atencion.telefono', '33445566', 'canalDeAtencion', []],
    'una version mal escrita' => ['version.android.minima', '1.8', 'politicaDeVersion', ['android']],
]);

it('sin cuentas, o con una en blanco, es un problema nuestro y no una lista vacia', function (array $cuentas) {
    // Una lista vacía le diría al socio que no hay dónde pagar.
    ConfiguracionDeEjemplo::cuentas($cuentas);
    $resultado = adaptador()->cuentasParaPagar();

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
})->with([
    'sin cuentas' => [[]],
    'una cuenta con banco en blanco' => [[['banco' => '   ', 'cuenta' => '1', 'orden' => 1]]],
]);

it('el detalle va al log y nombra la clave', function () {
    ConfiguracionDeEjemplo::quitar('titular.nit');
    $log = new LoggerEspia;
    (new ConfiguracionDesdeLaBase($log))->cuentasParaPagar();

    expect((string) json_encode($log->registros))->toContain('titular.nit');
});

it('una plataforma sin versiones deja pasar a todos', function () {
    $politica = adaptador()->politicaDeVersion('ios')->value();

    expect($politica->evaluar(Version::desde('0.0.1')))->toBe(EstadoDeVersion::Vigente)
        ->and($politica->urlTienda)->toBeNull();
});

it('sin razon social, area ni avisos salen los valores por defecto', function () {
    $politica = adaptador()->politicaDeVersion('android')->value();

    expect(adaptador()->cuentasParaPagar()->value()->razonSocialDelTitular)->toBe('Agropartners S.R.L.')
        ->and(adaptador()->canalDeAtencion()->value()->area)->toBe('Atención al Cliente')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionObligatoria)->titulo)->toBe('Actualización necesaria')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionObligatoria)->mensaje)->toBe('Esta versión dejó de estar disponible. Actualiza para seguir usando la App.')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionSugerida)->titulo)->toBe('Hay una versión nueva')
        ->and($politica->aviso(EstadoDeVersion::ActualizacionSugerida)->mensaje)->toBe('Hay una versión nueva de la App. Puedes actualizar cuando quieras.');
});

it('un valor cargado pisa el valor por defecto', function () {
    ConfiguracionDeEjemplo::poner('aviso.obligatoria.titulo', 'Actualizá la App');

    expect(adaptador()->politicaDeVersion('android')->value()->aviso(EstadoDeVersion::ActualizacionObligatoria)->titulo)
        ->toBe('Actualizá la App');
});

it('los valores se recortan', function () {
    ConfiguracionDeEjemplo::poner('version.android.minima', ' 1.8.0 ');
    ConfiguracionDeEjemplo::poner('atencion.telefono', ' 67701468 ');

    expect(adaptador()->politicaDeVersion('android')->value()->minima->texto())->toBe('1.8.0')
        ->and(adaptador()->canalDeAtencion()->value()->whatsappUrl())->toBe('https://wa.me/59167701468');
});

it('una clave que el codigo no conoce se ignora', function () {
    // SQLite no tiene el CHECK de claves: la fila entra y no tiene que romper nada.
    DB::table('maestros_configuracion_app')->insert(['clave' => 'otra.cosa', 'valor' => 'x']);

    expect(adaptador()->cuentasParaPagar()->isFailure())->toBeFalse();
});

it('si la base falla, la excepcion sube', function () {
    // Una base caída no es un dato que falta: confundirlos escondería la caída
    // detrás de CONFIGURACION_INCOMPLETA.
    Schema::drop('maestros_cuentas_bancarias');

    adaptador()->cuentasParaPagar();
})->throws(QueryException::class);

it('en SQL Server la clave compara exacto, con mayusculas incluidas', function () {
    // La colación de Azure no distingue mayúsculas: sin BIN2, 'Titular.NIT'
    // pasaría el CHECK de claves y el código, que compara exacto, la ignoraría.
    $script = (string) file_get_contents(database_path('sql/maestros-configuracion.sql'));

    expect($script)->toMatch('/\bclave\s+VARCHAR\(60\)\s+COLLATE\s+Latin1_General_BIN2\s+NOT\s+NULL/i');
});

it('el script acepta exactamente las claves que lee el codigo', function () {
    $script = (string) file_get_contents(database_path('sql/maestros-configuracion.sql'));
    preg_match('/CK_configuracion_app_clave\s+CHECK\s*\(\s*clave\s+IN\s*\(([^)]*)\)/i', $script, $lista);
    preg_match_all("/N?'([a-z_.]+)'/", $lista[1] ?? '', $claves);

    expect(collect($claves[1])->sort()->values()->all())
        ->toBe(collect(ConfiguracionDesdeLaBase::CLAVES)->sort()->values()->all())
        ->and(ConfiguracionDesdeLaBase::CLAVES)->toHaveCount(14);
});
