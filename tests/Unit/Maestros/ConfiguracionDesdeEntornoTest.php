<?php

declare(strict_types=1);

use Maestros\Domain\Aplicacion\AvisoDeVersion;
use Maestros\Domain\Aplicacion\EstadoDeVersion;
use Maestros\Domain\Aplicacion\PoliticaDeVersion;
use Maestros\Domain\Aplicacion\Version;
use Maestros\Domain\Atencion\CuentasParaPagar;
use Maestros\Infrastructure\Configuracion\ConfiguracionDesdeEntorno;
use Psr\Log\NullLogger;

/** @param array<string, string> $cambios */
function configuracion(array $cambios = []): ConfiguracionDesdeEntorno
{
    $valores = $cambios + [
        'android_minima' => '1.8.0',
        'android_recomendada' => '2.1.0',
        'bancos' => 'Banco Nacional de Bolivia|10000006816033; Banco BISA|701-5032719-3-81',
        'nit' => '1013879029',
        'telefono' => '67701468',
    ];

    return new ConfiguracionDesdeEntorno(
        [
            'android' => ['minima' => $valores['android_minima'], 'recomendada' => $valores['android_recomendada'], 'tienda' => 'https://play.google.com/x'],
            'ios' => ['minima' => '', 'recomendada' => '', 'tienda' => ''],
        ],
        new AvisoDeVersion('Actualización necesaria', 'm'),
        new AvisoDeVersion('Hay una versión nueva', 'm'),
        $valores['bancos'],
        'Agropartners S.R.L.',
        $valores['nit'],
        'Atención al Cliente',
        $valores['telefono'],
        new NullLogger,
    );
}

it('lee las cuentas separadas por punto y coma', function () {
    $cuentas = configuracion()->cuentasParaPagar()->value();

    expect($cuentas)->toBeInstanceOf(CuentasParaPagar::class)
        ->and($cuentas->cuentas)->toHaveCount(2)
        ->and($cuentas->cuentas[1]->banco)->toBe('Banco BISA')
        ->and($cuentas->cuentas[1]->numeroCuenta)->toBe('701-5032719-3-81');
});

it('una configuracion que falta o esta mal escrita es un problema nuestro, no una lista vacia', function (array $cambios, string $metodo) {
    // Una lista de bancos vacía le diría al socio que no hay dónde pagar; un
    // número fijo, que no hay a quién escribir. Mejor un error que lo delate.
    $resultado = configuracion($cambios)->{$metodo}();

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
})->with([
    'sin bancos' => [['bancos' => ''], 'cuentasParaPagar'],
    'un banco sin numero' => [['bancos' => 'Banco BISA'], 'cuentasParaPagar'],
    'sin NIT del titular' => [['nit' => ''], 'cuentasParaPagar'],
    'sin telefono de atencion' => [['telefono' => ''], 'canalDeAtencion'],
    'un telefono fijo' => [['telefono' => '33445566'], 'canalDeAtencion'],
]);

it('una version configurada mal escrita tambien es un problema nuestro', function () {
    $resultado = configuracion(['android_minima' => '1.8'])->politicaDeVersion('android');

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONFIGURACION_INCOMPLETA');
});

it('una plataforma sin versiones configuradas deja pasar a todos', function () {
    $politica = configuracion()->politicaDeVersion('ios')->value();

    expect($politica)->toBeInstanceOf(PoliticaDeVersion::class)
        ->and($politica->evaluar(Version::desde('0.0.1')))->toBe(EstadoDeVersion::Vigente)
        ->and($politica->urlTienda)->toBeNull();
});
