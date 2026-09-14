<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
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

it('reporta a quien le falta el usuario en el directorio', function () {
    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-8f2b1c40')
        ->assertExitCode(1);
});

it('no reporta nada cuando directorio y boveda estan completos', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');
    $this->directorio->crearOActualizar($persona, 'una-contrasena');
    app(BovedaDeContrasenas::class)->guardar($persona, 'una-contrasena');

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
