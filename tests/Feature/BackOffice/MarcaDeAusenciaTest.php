<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
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

function personaDe(string $id, string $nombre, string $celular, ?string $habilitadaEl): PersonaDeContacto
{
    return PersonaDeContacto::replica(
        IdDePersona::desde($id),
        CodigoDeSocio::desde('C-004871'),
        $nombre,
        Celular::desdeLocalBoliviano($celular),
        $habilitadaEl === null ? null : new DateTimeImmutable($habilitadaEl),
        new DateTimeImmutable('2026-09-15T12:00:00Z'),
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
    // Un celular distinto al de la semilla: la columna es unica, y el ultimo
    // test de este archivo importa el archivo de ejemplo.
    $contactos->save(personaDe('p-001', 'Monica Salvatierra', '70000001', '2026-09-01T10:00:00Z'));
    $contactos->save(personaDe('p-002', 'Jorge Pena', '67701468', '2026-09-01T10:00:00Z'));
    $contactos->save(personaDe('p-003', 'Ana Roca', '71234567', null));

    // p-001 vino en la ultima corrida; p-002 y p-003 quedaron en una anterior.
    ContactoRecord::query()->whereIn('id_de_persona', ['p-002', 'p-003'])
        ->update(['vista_en_importacion_el' => '2026-09-10 06:00:00']);
    ContactoRecord::query()->where('id_de_persona', 'p-001')
        ->update(['vista_en_importacion_el' => '2026-09-15 06:00:00']);

    SesionDeOperador::guardar(new Operador(
        IdDeOperador::desdeOid('oid-77'),
        'Operador',
        'o@agropartners.com.bo',
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

it('avisa sobre la habilitada que la ultima importacion no trajo', function () {
    $html = (string) $this->get('/admin/contactos')->assertOk()->getContent();

    expect(filaDe($html, 'Jorge Pena'))->toContain('No vino en la última importación');
});

it('no avisa sobre la que si vino', function () {
    $html = (string) $this->get('/admin/contactos')->assertOk()->getContent();

    expect(filaDe($html, 'Monica Salvatierra'))->not->toContain('No vino en la última importación');
});

it('no avisa sobre quien no tiene acceso, aunque tampoco haya venido', function () {
    // Que falte alguien que nunca pudo entrar no le cambia nada a nadie.
    $html = (string) $this->get('/admin/contactos')->assertOk()->getContent();

    expect(filaDe($html, 'Ana Roca'))->not->toContain('No vino en la última importación');
});

it('la importacion marca como vista a una fila que omite por vieja', function () {
    $archivo = database_path('semillas/maestros-ejemplo.json');

    // Dos corridas del mismo archivo: la segunda omite todo por no ser mas
    // nueva, y aun asi tiene que dejar constancia de que la vio.
    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);
    $antes = ContactoRecord::query()->find('p-8f2b1c40')->vista_en_importacion_el;

    ContactoRecord::query()->where('id_de_persona', 'p-8f2b1c40')
        ->update(['vista_en_importacion_el' => '2020-01-01 00:00:00']);

    $this->artisan('maestros:importar', ['archivo' => $archivo])
        ->expectsOutputToContain('Omitidos por ser más viejos')
        ->assertExitCode(0);

    $despues = ContactoRecord::query()->find('p-8f2b1c40')->vista_en_importacion_el;

    expect($antes)->not->toBeNull()
        ->and($despues->format('Y'))->not->toBe('2020');
});
