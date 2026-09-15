<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

/**
 * Le da acceso completo a toda persona que la réplica marca habilitada.
 *
 * Va por el puerto en vez de nombrar personas: «completo» tiene que seguir
 * significando completo cuando la semilla cambie, y no «alcanza con Monica».
 */
function darAccesoATodasLasHabilitadas(DirectorioFalso $directorio): void
{
    foreach (app(DirectorioDeContactos::class)->habilitadas() as $persona) {
        $directorio->crearOActualizar($persona, 'una-contrasena');
        app(BovedaDeContrasenas::class)->guardar($persona, 'una-contrasena');
    }
}

it('reporta a quien le falta el usuario en el directorio', function () {
    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-8f2b1c40')
        ->assertExitCode(1);
});

it('no reporta nada cuando directorio y boveda estan completos', function () {
    darAccesoATodasLasHabilitadas($this->directorio);

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin discrepancias')
        ->assertExitCode(0);
});

it('reporta a quien tiene usuario pero no contrasena guardada', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin contraseña guardada')
        ->assertExitCode(1);
});

it('reporta a quien quedo deshabilitado en el directorio', function () {
    // Keycloak no borra al usuario: le pone enabled=false. Preguntar si existe
    // lo encuentra igual, y por eso esto pasaba desapercibido.
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');
    app(BovedaDeContrasenas::class)->guardar($persona, 'una-contrasena');
    $this->directorio->deshabilitar($persona);

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-8f2b1c40')
        ->assertExitCode(1);
});

it('reporta a quien conserva credencial sin estar habilitado en la replica', function () {
    // La direccion que el plan prometia y nadie implemento: si SAP deja de
    // marcar habilitada a una persona, su credencial y su usuario siguen ahi,
    // y hasta ahora nada lo decia.
    $persona = IdDePersona::desde('p-fantasma');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');
    app(BovedaDeContrasenas::class)->guardar($persona, 'una-contrasena');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-fantasma')
        ->assertExitCode(1);
});

it('no confunde con discrepancia a quien nunca tuvo credencial', function () {
    // Las no habilitadas de la réplica no tienen credencial y no deben aparecer:
    // la conciliación solo habla de estados a medias, no de quien nunca entró.
    darAccesoATodasLasHabilitadas($this->directorio);

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin discrepancias')
        ->assertExitCode(0);
});
