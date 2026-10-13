<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// Habilitado es tener credencial: un usuario activo en el directorio y su
// contraseña en la bóveda, con el id del número. La conciliación compara las
// dos cosas en las dos direcciones.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
    $this->darAcceso = function (string $id): void {
        $this->directorio->crearOActualizar(IdDePersona::desde($id), 'una-contrasena');
        app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde($id), 'una-contrasena');
    };
});

it('sin discrepancias cuando cada numero con credencial tiene usuario activo', function () {
    ($this->darAcceso)(ReplicaDeEjemplo::MONICA);

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Sin discrepancias.')
        ->assertExitCode(0);
});

it('avisa una credencial cuyo usuario esta inactivo', function () {
    ($this->darAcceso)(ReplicaDeEjemplo::MONICA);
    $this->directorio->deshabilitar(IdDePersona::desde(ReplicaDeEjemplo::MONICA));

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Credencial guardada pero el usuario no está activo en el directorio: n-59170741828')
        ->assertExitCode(1);
});

it('avisa un usuario activo sin credencial', function () {
    $this->directorio->crearOActualizar(IdDePersona::desde(ReplicaDeEjemplo::MONICA), 'una-contrasena');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Usuario activo en el directorio sin credencial guardada: n-59170741828')
        ->assertExitCode(1);
});

it('avisa una credencial con id por contacto', function () {
    ($this->darAcceso)('p-8f2b1c40');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Credencial con id por contacto, anterior al cambio a números: p-8f2b1c40')
        ->assertExitCode(1);
});

it('informa sin contarlo un numero habilitado que hoy no ingresa', function () {
    ($this->darAcceso)(ReplicaDeEjemplo::MONICA);
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('Habilitado pero hoy no ingresa (varios-grupos): n-59170741828')
        ->expectsOutputToContain('Sin discrepancias.')
        ->assertExitCode(0);
});

it('falla si el directorio no responde', function () {
    ($this->darAcceso)(ReplicaDeEjemplo::MONICA);
    $this->directorio->caido = true;

    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('El directorio de identidades no respondió')
        ->assertExitCode(1);
});
