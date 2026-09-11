<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

final class DirectorioFalso implements DirectorioDeIdentidades
{
    public bool $caido = false;

    /** @var array<string, string> */
    public array $usuarios = [];

    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        $this->usuarios[$persona->value()] = $contrasena;

        return Result::success();
    }

    public function deshabilitar(IdDePersona $persona): Result
    {
        unset($this->usuarios[$persona->value()]);

        return Result::success();
    }

    public function existe(IdDePersona $persona): bool
    {
        return isset($this->usuarios[$persona->value()]);
    }
}

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
});

it('crea el usuario en el directorio y guarda la contrasena cifrada', function () {
    $persona = IdDePersona::desde('p-8f2b1c40');

    expect(app(Mediator::class)->send(new HabilitarPersona($persona))->isSuccess)->toBeTrue()
        ->and($this->directorio->existe($persona))->toBeTrue();

    $guardada = app(BovedaDeContrasenas::class)->leer($persona);

    expect($guardada)->toBeString()
        ->and(strlen((string) $guardada))->toBeGreaterThanOrEqual(24)
        ->and($guardada)->toBe($this->directorio->usuarios['p-8f2b1c40']);
});

it('no guarda contrasena si el directorio falla', function () {
    $this->directorio->caido = true;
    $persona = IdDePersona::desde('p-8f2b1c40');

    $resultado = app(Mediator::class)->send(new HabilitarPersona($persona));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE')
        ->and(app(BovedaDeContrasenas::class)->leer($persona))->toBeNull();
});

it('rechaza habilitar a alguien que no existe en la replica', function () {
    $resultado = app(Mediator::class)->send(new HabilitarPersona(IdDePersona::desde('p-fantasma')));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});

it('conciliar reporta a quien le falta el usuario en el directorio', function () {
    $this->artisan('identidad:conciliar')
        ->expectsOutputToContain('p-8f2b1c40')
        ->assertExitCode(1);
});
