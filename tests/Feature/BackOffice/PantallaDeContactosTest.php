<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Infrastructure\Persistence\AsientoRecord;
use BackOffice\Presentation\Http\SesionDeOperador;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
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
use Tests\Dobles\DirectorioFalso;

uses(RefreshDatabase::class);

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
    $contactos->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-001'),
        CodigoDeSocio::desde('C-004871'),
        'Monica Salvatierra',
        Celular::desdeLocalBoliviano('70741828'),
        new DateTimeImmutable('2026-09-01T10:00:00Z'),
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

    // Una fila con celular inservible solo se consigue escribiendo por fuera
    // del dominio, que es justo el caso que la pantalla tiene que mostrar.
    ContactoRecord::query()->where('id_de_persona', 'p-003')->update(['celular' => '12345']);

    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);

    SesionDeOperador::guardar(new Operador(
        IdDeOperador::desdeOid('oid-77'),
        'Jorge Pena',
        'jorge@agropartners.com.bo',
    ));
});

it('lista las personas con su socio', function () {
    $this->get('/admin/contactos')
        ->assertOk()
        ->assertSee('Monica Salvatierra')
        ->assertSee('Sebastian Monasterio')
        ->assertSee('Ana Roca');
});

it('muestra el celular completo sin enmascarar', function () {
    $this->get('/admin/contactos')->assertSee('+59170741828');
});

it('filtra por texto', function () {
    $this->get('/admin/contactos?q=Ana')
        ->assertSee('Ana Roca')
        ->assertDontSee('Monica Salvatierra');
});

it('filtra por estado', function () {
    $this->get('/admin/contactos?estado=habilitadas')
        ->assertSee('Monica Salvatierra')
        ->assertDontSee('Ana Roca');
});

it('no ofrece habilitar a quien no tiene celular valido', function () {
    $this->get('/admin/contactos')->assertSee('Sin celular válido en SAP', false);
});

it('un parametro de pagina basura no rompe la pantalla', function () {
    $this->get('/admin/contactos?pagina=-3')->assertOk();
    $this->get('/admin/contactos?estado=inventado')->assertOk();
});

it('habilitar a quien no tiene celular valido no asienta nada', function () {
    $this->post('/admin/contactos/p-003/habilitar')->assertRedirect(route('admin.contactos'));

    expect(AsientoRecord::query()->count())->toBe(0);
});

it('habilitar a quien si puede entrar asienta en la bitacora', function () {
    app(ContactoRepository::class)->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-004'),
        CodigoDeSocio::desde('C-004871'),
        'Carlos Vaca',
        Celular::desdeLocalBoliviano('76543210'),
        null,
        new DateTimeImmutable('2026-09-15T12:00:00Z'),
    ));

    $this->post('/admin/contactos/p-004/habilitar')->assertRedirect(route('admin.contactos'));

    expect(AsientoRecord::query()->count())->toBe(1)
        ->and(AsientoRecord::query()->first()->accion)->toBe('concedio')
        ->and(AsientoRecord::query()->first()->operador)->toBe('Jorge Pena');
});

it('volver_a no puede mandar a un sitio ajeno', function () {
    // Viene del formulario: sin acotarlo, el boton salta a donde quiera quien
    // arme el POST.
    $this->post('/admin/contactos/p-003/habilitar', ['volver_a' => 'https://evil.example/phish'])
        ->assertRedirect(route('admin.contactos'));
});

it('volver_a conserva la busqueda del operador', function () {
    $this->post('/admin/contactos/p-003/habilitar', ['volver_a' => '/admin/contactos?q=Ana&pagina=2'])
        ->assertRedirect(url('/admin/contactos?q=Ana&pagina=2'));
});
