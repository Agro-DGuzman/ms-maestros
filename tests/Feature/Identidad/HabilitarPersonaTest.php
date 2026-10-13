<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\DirectorioFalso;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// En la semilla, el +59170741828 es Mónica (p-8f2b1c40), contacto de C-004871,
// del grupo GRP-014. Lo que se habilita es el número.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);
    $this->habilitar = fn (string $id) => app(Mediator::class)->send(new HabilitarPersona(IdDePersona::desde($id)));
});

it('habilitar un numero crea la credencial con el id del numero', function () {
    expect(($this->habilitar)(ReplicaDeEjemplo::MONICA)->isSuccess)->toBeTrue()
        ->and($this->directorio->estaActivo(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBeTrue();

    $guardada = app(BovedaDeContrasenas::class)->leer(IdDePersona::desde(ReplicaDeEjemplo::MONICA));

    expect($guardada)->toBeString()
        ->and(strlen((string) $guardada))->toBeGreaterThanOrEqual(24)
        ->and($guardada)->toBe($this->directorio->usuarios[ReplicaDeEjemplo::MONICA]);
});

it('no guarda contrasena si el directorio falla', function () {
    $this->directorio->caido = true;

    $resultado = ($this->habilitar)(ReplicaDeEjemplo::MONICA);

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE')
        ->and(app(BovedaDeContrasenas::class)->leer(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBeNull();
});

it('habilita un numero que comparten contactos de socios del mismo grupo', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');

    expect(($this->habilitar)(ReplicaDeEjemplo::MONICA)->isSuccess)->toBeTrue();
});

it('no habilita un numero en socios de dos grupos', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    $resultado = ($this->habilitar)(ReplicaDeEjemplo::MONICA);

    expect($resultado->error->code)->toBe('NUMERO_EN_VARIOS_GRUPOS')
        ->and($this->directorio->usuarios)->toBe([]);
});

it('no habilita un numero que solo es contacto de socios sin grupo', function () {
    ReplicaDeEjemplo::sinGrupo('C-004871');

    expect(($this->habilitar)(ReplicaDeEjemplo::MONICA)->error->code)->toBe('NUMERO_SOLO_SIN_GRUPO')
        ->and($this->directorio->usuarios)->toBe([]);
});

it('no habilita un numero sin contactos visibles', function () {
    ReplicaDeEjemplo::personaDadaDeBaja('p-8f2b1c40');

    expect(($this->habilitar)(ReplicaDeEjemplo::MONICA)->error->code)->toBe('NUMERO_SIN_CONTACTOS')
        ->and($this->directorio->usuarios)->toBe([]);
});

it('no habilita un id por contacto', function () {
    expect(($this->habilitar)('p-8f2b1c40')->error->code)->toBe('CELULAR_INVALIDO')
        ->and($this->directorio->usuarios)->toBe([]);
});

it('el comando recibe el celular', function () {
    $this->artisan('identidad:habilitar', ['celular' => '70741828'])->assertExitCode(0);

    expect($this->directorio->estaActivo(IdDePersona::desde(ReplicaDeEjemplo::MONICA)))->toBeTrue();
});

it('el comando rechaza lo que no es un celular movil', function () {
    $this->artisan('identidad:habilitar', ['celular' => '12345'])->assertExitCode(1);

    expect($this->directorio->usuarios)->toBe([]);
});
