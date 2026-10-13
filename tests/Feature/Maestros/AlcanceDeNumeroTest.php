<?php

declare(strict_types=1);

use Identidad\Application\Contracts\DirectorioDeContactos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\MotivoDeNoIngreso;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Infrastructure\Alcance\ResolutorPorNumero;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// En la semilla, Mónica es el contacto p-8f2b1c40 del socio C-004871 (grupo
// GRP-014), con el +59170741828. C-004872 es del mismo grupo; C-005210, del GRP-021.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->alcance = fn () => app(ContactoRepository::class)->alcanceDeNumero(Celular::desdeLocalBoliviano('70741828'));
    $this->socios = fn () => array_map(static fn (CodigoDeSocio $s): string => $s->value(), ($this->alcance)()->socios());
});

it('el numero de Monica entra por su socio y nada mas', function () {
    $alcance = ($this->alcance)();

    expect($alcance->entra())->toBeTrue()
        ->and($alcance->grupo()?->value())->toBe('GRP-014')
        ->and(($this->socios)())->toBe(['C-004871']);
});

it('el mismo numero en otro socio del grupo suma ese socio', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');

    expect(($this->socios)())->toBe(['C-004871', 'C-004872']);
});

it('el mismo numero en un socio de otro grupo no entra', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    expect(($this->alcance)()->motivo())->toBe(MotivoDeNoIngreso::VariosGrupos);
});

it('un socio sin grupo no suma ni bloquea', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');
    ReplicaDeEjemplo::sinGrupo('C-004872');

    expect(($this->socios)())->toBe(['C-004871']);
});

it('si solo queda en socios sin grupo no entra', function () {
    ReplicaDeEjemplo::sinGrupo('C-004871');

    expect(($this->alcance)()->motivo())->toBe(MotivoDeNoIngreso::SoloSinGrupo);
});

it('un contacto dado de baja no cuenta', function () {
    ReplicaDeEjemplo::personaDadaDeBaja('p-8f2b1c40');

    expect(($this->alcance)()->motivo())->toBe(MotivoDeNoIngreso::SinContactos);
});

it('un socio inactivo no cuenta', function () {
    ReplicaDeEjemplo::socioInactivo('C-004871');

    expect(($this->alcance)()->motivo())->toBe(MotivoDeNoIngreso::SinContactos);
});

it('el resolutor alcanza solo los socios del numero', function () {
    $resolutor = app(ResolutorPorNumero::class);

    expect($resolutor->alcanza(IdDePersona::desde(ReplicaDeEjemplo::MONICA), CodigoDeSocio::desde('C-004871')))->toBeTrue()
        ->and($resolutor->alcanza(IdDePersona::desde(ReplicaDeEjemplo::MONICA), CodigoDeSocio::desde('C-004872')))->toBeFalse()
        // Un id por contacto ya no identifica a una persona: no alcanza nada.
        ->and($resolutor->alcanza(IdDePersona::desde('p-8f2b1c40'), CodigoDeSocio::desde('C-004871')))->toBeFalse();
});

it('el directorio dice por que un numero no entra', function () {
    $directorio = app(DirectorioDeContactos::class);
    $celular = Celular::desdeLocalBoliviano('70741828');

    expect($directorio->motivoDeNoIngreso($celular))->toBeNull();

    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    expect($directorio->motivoDeNoIngreso($celular))->toBe(MotivoDeNoIngreso::VariosGrupos);
});
