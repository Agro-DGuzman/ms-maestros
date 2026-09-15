<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Contactos\FiltroDeEstado;
use Maestros\Application\Contactos\ListarContactos\ListarContactos;
use Maestros\Application\Contactos\PaginaDeContactos;
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
    $marca = new DateTimeImmutable('2026-09-15T12:00:00Z');

    app(GrupoRepository::class)->save(
        GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Grupo Monasterio', $marca),
    );

    $socios = app(SocioRepository::class);
    $socios->save(Socio::replica(
        CodigoDeSocio::desde('C-004871'),
        RazonSocial::desde('Sebastian Monasterio'),
        IdDeGrupo::desde('GRP-014'),
        $marca,
    ));
    $socios->save(Socio::replica(
        CodigoDeSocio::desde('C-004872'),
        RazonSocial::desde('Agricola del Este'),
        IdDeGrupo::desde('GRP-020'),
        $marca,
    ));

    $contactos = app(ContactoRepository::class);
    $contactos->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-001'),
        CodigoDeSocio::desde('C-004871'),
        'Monica Salvatierra',
        Celular::desdeLocalBoliviano('70741828'),
        new DateTimeImmutable('2026-09-01T10:00:00Z'),
        $marca,
    ));
    $contactos->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-002'),
        CodigoDeSocio::desde('C-004872'),
        'Jorge Pena',
        Celular::desdeLocalBoliviano('67701468'),
        null,
        $marca,
    ));
    $contactos->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-003'),
        CodigoDeSocio::desde('C-004871'),
        'Ana Roca',
        Celular::desdeLocalBoliviano('71234567'),
        null,
        $marca,
    ));
});

function listar(?string $texto, FiltroDeEstado $estado, int $pagina = 1, int $tamano = 25): PaginaDeContactos
{
    $resultado = app(Mediator::class)->send(
        new ListarContactos(CriterioDeBusqueda::de($texto, $estado, $pagina, $tamano)),
    );

    expect($resultado->isSuccess)->toBeTrue();

    return $resultado->value();
}

it('sin filtros devuelve todas con el total', function () {
    $pagina = listar(null, FiltroDeEstado::Todas);

    expect($pagina->total)->toBe(3)->and($pagina->items)->toHaveCount(3);
});

it('filtra por estado de habilitacion', function () {
    $habilitadas = listar(null, FiltroDeEstado::Habilitadas);
    $noHabilitadas = listar(null, FiltroDeEstado::NoHabilitadas);

    expect($habilitadas->total)->toBe(1)
        ->and($habilitadas->items[0]->idDePersona)->toBe('p-001')
        ->and($habilitadas->items[0]->estaHabilitada)->toBeTrue()
        ->and($noHabilitadas->total)->toBe(2);
});

it('busca por nombre de la persona sin importar mayusculas', function () {
    $pagina = listar('salvatierra', FiltroDeEstado::Todas);

    expect($pagina->total)->toBe(1)->and($pagina->items[0]->idDePersona)->toBe('p-001');
});

it('busca por razon social del socio', function () {
    $pagina = listar('Agricola', FiltroDeEstado::Todas);

    expect($pagina->total)->toBe(1)->and($pagina->items[0]->idDePersona)->toBe('p-002');
});

it('busca por el celular normalizado', function () {
    $pagina = listar('70741828', FiltroDeEstado::Todas);

    expect($pagina->total)->toBe(1)->and($pagina->items[0]->idDePersona)->toBe('p-001');
});

it('trae el socio y el grupo economico de cada fila', function () {
    $fila = listar('salvatierra', FiltroDeEstado::Todas)->items[0];

    expect($fila->cardCode)->toBe('C-004871')
        ->and($fila->razonSocial)->toBe('Sebastian Monasterio')
        ->and($fila->grupoEconomico)->toBe('Grupo Monasterio');
});

it('deriva las iniciales en vez de leerlas de una columna', function () {
    expect(listar('salvatierra', FiltroDeEstado::Todas)->items[0]->iniciales)->toBe('MS');
});

it('un socio sin grupo conocido no rompe la fila', function () {
    $fila = listar('Agricola', FiltroDeEstado::Todas)->items[0];

    expect($fila->grupoEconomico)->toBeNull()
        ->and($fila->razonSocial)->toBe('Agricola del Este');
});

it('pagina los resultados conservando el total', function () {
    $primera = listar(null, FiltroDeEstado::Todas, 1, 2);
    $segunda = listar(null, FiltroDeEstado::Todas, 2, 2);

    expect($primera->items)->toHaveCount(2)
        ->and($segunda->items)->toHaveCount(1)
        ->and($segunda->total)->toBe(3)
        ->and($segunda->totalDePaginas())->toBe(2);
});

it('el celular replicado se reporta como valido', function () {
    $fila = listar('salvatierra', FiltroDeEstado::Todas)->items[0];

    expect($fila->celular)->toBe('+59170741828')
        ->and($fila->celularEsValido)->toBeTrue();
});
