<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\BuzonDeEntrada;
use Maestros\Application\Eventos\PublicadorDeEventos;
use Tests\Dobles\BuzonEnMemoria;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\Soporte\SobresDePropiedad;

uses(RefreshDatabase::class);

/** Valores de mentira: alcanza con que no estén vacíos. */
function configuracionDeBusCompleta(): void
{
    config([
        'servicebus.namespace' => 'agp-pruebas',
        'servicebus.escucha.nombre' => 'ms-maestros-escucha',
        'servicebus.escucha.clave' => 'clave-de-escucha',
        'servicebus.publicacion.nombre' => 'ms-maestros-publica',
        'servicebus.publicacion.clave' => 'clave-de-publicacion',
    ]);
}

it('sin configuracion de Service Bus no arranca y dice que falta', function (string $clave, string $variable) {
    configuracionDeBusCompleta();
    config([$clave => '']);

    $this->artisan('eventos:consumir --una-vez')
        ->expectsOutputToContain($variable)
        ->assertExitCode(1);
})->with([
    'namespace' => ['servicebus.namespace', 'SERVICEBUS_NAMESPACE'],
    'nombre de escucha' => ['servicebus.escucha.nombre', 'SERVICEBUS_SAS_NOMBRE'],
    'clave de escucha' => ['servicebus.escucha.clave', 'SERVICEBUS_SAS_CLAVE'],
    'nombre de publicacion' => ['servicebus.publicacion.nombre', 'SERVICEBUS_PUBLICACION_SAS_NOMBRE'],
    'clave de publicacion' => ['servicebus.publicacion.clave', 'SERVICEBUS_PUBLICACION_SAS_CLAVE'],
]);

it('con la base y el bus de dobles hace una vuelta y sale', function () {
    configuracionDeBusCompleta();
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $this->app->instance(BuzonDeEntrada::class, $buzon);
    $this->app->instance(PublicadorDeEventos::class, new PublicadorEnMemoria);

    $this->artisan('eventos:consumir --una-vez')->assertExitCode(0);

    expect($buzon->completados)->toHaveCount(1)
        ->and(DB::table('maestros_propiedad')->where('id_propiedad', 'PROP-014')->value('estado'))->toBe('activo');
});
