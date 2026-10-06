<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->socios = app(SocioRepository::class);
    $this->contactos = app(ContactoRepository::class);
    $this->vigencia = new DateTimeImmutable('2026-10-05T12:00:00Z');
});

function contactoDePrueba(string $id, string $socio, ?string $celular, DateTimeImmutable $vigencia): PersonaDeContacto
{
    return PersonaDeContacto::replica(
        IdDePersona::desde($id),
        CodigoDeSocio::desde($socio),
        'Contacto de prueba',
        $celular === null ? null : Celular::desdeLocalBoliviano($celular),
        null,
        $vigencia,
    );
}

it('un socio sin grupo se guarda y se relee sin grupo', function () {
    $this->socios->save(Socio::replica(
        CodigoDeSocio::desde('C-900001'), RazonSocial::desde('Agro Prueba SRL'), null, $this->vigencia,
    ));

    expect($this->socios->find(CodigoDeSocio::desde('C-900001'))?->grupo())->toBeNull();
});

it('el estado, la baja y el origen del socio se conservan', function () {
    $baja = new DateTimeImmutable('2026-10-05T13:00:00Z');

    $this->socios->save(Socio::replica(
        CodigoDeSocio::desde('C-900001'), RazonSocial::desde('Agro Prueba SRL'), IdDeGrupo::desde('GRP-014'), $this->vigencia,
        activo: false, dadoDeBajaEl: $baja, origenEsquema: 'AGRO_P6', origenEventoId: 1842,
    ));

    $releido = $this->socios->find(CodigoDeSocio::desde('C-900001'));

    expect($releido?->activo())->toBeFalse()
        ->and($releido?->dadoDeBajaEl())->toEqual($baja)
        ->and($releido?->origenEsquema())->toBe('AGRO_P6')
        ->and($releido?->origenEventoId())->toBe(1842);
});

it('un grupo conserva su segmento y admite un codigo de 50 caracteres', function () {
    $codigo = str_repeat('G', 50);

    app(GrupoRepository::class)->save(GrupoEconomico::replica(
        IdDeGrupo::desde($codigo), 'Grupo largo', $this->vigencia, 'Agroindustrial',
    ));

    expect(app(GrupoRepository::class)->find(IdDeGrupo::desde($codigo))?->segmento())->toBe('Agroindustrial');
});

it('dos contactos con el mismo celular se guardan los dos', function () {
    // El celular dejó de ser único: un conflicto se acepta y se bloquea al
    // ingresar, no se rechaza al guardar.
    $this->contactos->save(contactoDePrueba('p-1523', 'C-004871', '70741828', $this->vigencia));

    expect($this->contactos->find(IdDePersona::desde('p-1523')))->not->toBeNull()
        ->and($this->contactos->find(IdDePersona::desde('p-8f2b1c40')))->not->toBeNull();
});

it('un contacto sin celular se guarda y se relee sin celular', function () {
    $this->contactos->save(contactoDePrueba('p-1524', 'C-004871', null, $this->vigencia));

    expect($this->contactos->find(IdDePersona::desde('p-1524'))?->celular())->toBeNull();
});

it('dar de baja los que no vinieron toca solo ese socio y respeta bajas previas', function () {
    $antes = new DateTimeImmutable('2026-01-01T00:00:00Z');
    $this->contactos->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-1599'), CodigoDeSocio::desde('C-004871'), 'Ya dado de baja', null, null, $this->vigencia,
        dadoDeBajaEl: $antes,
    ));

    $momento = new DateTimeImmutable('2026-10-05T15:00:00Z');
    $this->contactos->darDeBajaLosQueNoVinieron(CodigoDeSocio::desde('C-004871'), [IdDePersona::desde('p-a1b2c301')], $momento);

    expect($this->contactos->find(IdDePersona::desde('p-a1b2c301'))?->dadoDeBajaEl())->toBeNull()
        ->and($this->contactos->find(IdDePersona::desde('p-8f2b1c40'))?->dadoDeBajaEl())->toEqual($momento)
        ->and($this->contactos->find(IdDePersona::desde('p-1599'))?->dadoDeBajaEl())->toEqual($antes)
        ->and($this->contactos->find(IdDePersona::desde('p-a1b2c303'))?->dadoDeBajaEl())->toBeNull();
});
