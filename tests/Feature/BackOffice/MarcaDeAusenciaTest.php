<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use BackOffice\Presentation\Http\SesionDeOperador;
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
use Maestros\Infrastructure\Persistence\ContactoRecord;

uses(RefreshDatabase::class);

/*
 * Con la ingesta, que alguien ya no esté en SAP es una baja explícita, no la
 * ausencia en la última corrida del importador. La pantalla muestra el estado
 * de todo contacto que la App ya no ve, y lo pinta en rojo solo cuando esa
 * persona todavía tiene acceso: ahí hay algo que hacer.
 */

function personaDe(string $id, string $nombre, string $celular, ?string $habilitadaEl, ?string $bajaEl = null, bool $activa = true): PersonaDeContacto
{
    return PersonaDeContacto::replica(
        IdDePersona::desde($id),
        CodigoDeSocio::desde('C-004871'),
        $nombre,
        Celular::desdeLocalBoliviano($celular),
        $habilitadaEl === null ? null : new DateTimeImmutable($habilitadaEl),
        new DateTimeImmutable('2026-09-15T12:00:00Z'),
        activa: $activa,
        dadoDeBajaEl: $bajaEl === null ? null : new DateTimeImmutable($bajaEl),
    );
}

beforeEach(function () {
    $marca = new DateTimeImmutable('2026-09-15T12:00:00Z');

    app(GrupoRepository::class)->save(
        GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Grupo Monasterio', $marca),
    );
    app(SocioRepository::class)->save(Socio::replica(
        CodigoDeSocio::desde('C-004871'),
        RazonSocial::desde('Sebastian Monasterio'),
        IdDeGrupo::desde('GRP-014'),
        $marca,
    ));

    $contactos = app(ContactoRepository::class);
    $contactos->save(personaDe('p-001', 'Monica Salvatierra', '70000001', '2026-09-01T10:00:00Z'));
    $contactos->save(personaDe('p-002', 'Jorge Pena', '67701468', '2026-09-01T10:00:00Z', bajaEl: '2026-10-05T12:00:00Z'));
    $contactos->save(personaDe('p-003', 'Ana Roca', '71234567', null, bajaEl: '2026-10-05T12:00:00Z'));
    $contactos->save(personaDe('p-004', 'Luis Paz', '71234568', '2026-09-01T10:00:00Z', activa: false));

    SesionDeOperador::guardar(new Operador(
        IdDeOperador::desdeOid('oid-77'),
        'Operador',
        'o@agropartners.com.bo',
        Permiso::todos(),
    ));
});

function filaDe(string $html, string $nombre): string
{
    $partes = explode('<tr>', $html);

    foreach ($partes as $parte) {
        if (str_contains($parte, $nombre)) {
            return $parte;
        }
    }

    return '';
}

function pantallaDeContactos(): string
{
    return (string) test()->get('/admin/contactos')->assertOk()->getContent();
}

it('avisa en rojo sobre la habilitada que SAP dio de baja', function () {
    expect(filaDe(pantallaDeContactos(), 'Jorge Pena'))->toContain('rojo')->toContain('Dada de baja en SAP');
});

it('avisa en rojo sobre la habilitada que SAP marco inactiva', function () {
    expect(filaDe(pantallaDeContactos(), 'Luis Paz'))->toContain('rojo')->toContain('Inactiva en SAP');
});

it('muestra el estado sin alarma de quien no tiene acceso', function () {
    $fila = filaDe(pantallaDeContactos(), 'Ana Roca');

    expect($fila)->toContain('Dada de baja en SAP')
        ->and($fila)->not->toContain('class="rojo" style="font-size:12px;">Dada de baja');
});

it('no marca nada sobre la que sigue en SAP', function () {
    $fila = filaDe(pantallaDeContactos(), 'Monica Salvatierra');

    expect($fila)->not->toContain('en SAP')
        ->and($fila)->not->toContain('Celular en conflicto');
});

it('marca a los dos contactos visibles que comparten celular', function () {
    app(ContactoRepository::class)->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-005'), CodigoDeSocio::desde('C-004871'), 'Rosa Vaca',
        Celular::desdeLocalBoliviano('70000001'), null, new DateTimeImmutable('2026-09-15T12:00:00Z'),
    ));

    $html = pantallaDeContactos();

    expect(filaDe($html, 'Monica Salvatierra'))->toContain('Celular en conflicto')
        ->and(filaDe($html, 'Rosa Vaca'))->toContain('Celular en conflicto');
});

it('un celular compartido con alguien dado de baja no esta en conflicto', function () {
    ContactoRecord::query()->where('id_de_persona', 'p-003')->update(['celular' => '+59170000001']);

    expect(filaDe(pantallaDeContactos(), 'Monica Salvatierra'))->not->toContain('Celular en conflicto');
});

it('ya no habla de la ultima importacion', function () {
    expect(pantallaDeContactos())->not->toContain('No vino en la última importación');
});
