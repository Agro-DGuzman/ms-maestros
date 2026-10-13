<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
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

    // Otro socio y otro contacto, para los casos de un número compartido.
    $this->socio = function (string $cardCode, string $grupo, string $nombreDelGrupo) use ($marca): void {
        app(GrupoRepository::class)->save(GrupoEconomico::replica(IdDeGrupo::desde($grupo), $nombreDelGrupo, $marca));
        app(SocioRepository::class)->save(Socio::replica(CodigoDeSocio::desde($cardCode), RazonSocial::desde("Socio {$cardCode}"), IdDeGrupo::desde($grupo), $marca));
    };
    $this->contacto = function (string $id, string $cardCode, string $nombre, string $celular) use ($marca): void {
        app(ContactoRepository::class)->save(PersonaDeContacto::replica(IdDePersona::desde($id), CodigoDeSocio::desde($cardCode), $nombre, Celular::desdeLocalBoliviano($celular), null, $marca));
    };
    $this->fila = function (string $html, string $nombre): string {
        foreach (explode('<tr>', $html) as $parte) {
            if (str_contains($parte, $nombre)) {
                return $parte;
            }
        }

        return '';
    };

    $this->directorio = new DirectorioFalso;
    $this->app->instance(DirectorioDeIdentidades::class, $this->directorio);

    SesionDeOperador::guardar(new Operador(
        IdDeOperador::desdeOid('oid-77'),
        'Jorge Pena',
        'jorge@agropartners.com.bo',
        Permiso::todos(),
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

it('no quedan el filtro ni la columna de habilitadas en SAP', function () {
    $this->get('/admin/contactos')
        ->assertDontSee('Habilitadas en SAP')
        ->assertDontSee('<th>En SAP</th>', false);
});

it('dar acceso habilita el numero y quitarlo lo quita', function () {
    $this->get('/admin/contactos?q=Monica')->assertSee('Dar acceso');

    $this->post('/admin/contactos/n-59170741828/habilitar')->assertRedirect(route('admin.contactos'));

    $this->get('/admin/contactos?q=Monica')->assertSee('Quitar acceso');

    $this->post('/admin/contactos/n-59170741828/deshabilitar')->assertRedirect(route('admin.contactos'));

    $this->get('/admin/contactos?q=Monica')->assertSee('Dar acceso');
});

it('dar acceso en una fila habilita el numero y todas sus filas lo muestran', function () {
    ($this->socio)('C-004872', 'GRP-014', 'Grupo Monasterio');
    ($this->contacto)('p-002', 'C-004872', 'Monica Otra', '70741828');

    $this->post('/admin/contactos/n-59170741828/habilitar');

    $html = (string) $this->get('/admin/contactos?q=70741828')->getContent();

    expect(substr_count($html, 'Quitar acceso'))->toBe(2)
        ->and($html)->not->toContain('Dar acceso');
});

it('cada fila dice con cuantos comparte el numero', function () {
    ($this->socio)('C-004872', 'GRP-014', 'Grupo Monasterio');
    ($this->contacto)('p-002', 'C-004872', 'Monica Otra', '70741828');

    $html = (string) $this->get('/admin/contactos?q=70741828')->getContent();

    expect(($this->fila)($html, 'Monica Salvatierra'))->toContain('Comparte el número con 1 contacto(s) más')
        ->and(($this->fila)($html, 'Monica Otra'))->toContain('Comparte el número con 1 contacto(s) más');
});

it('un numero en dos grupos se marca y no ofrece dar acceso', function () {
    ($this->socio)('C-005210', 'GRP-021', 'Grupo Vargas');
    ($this->contacto)('p-006', 'C-005210', 'Monica Vargas', '70741828');

    $this->get('/admin/contactos?q=70741828')
        ->assertSee('Contacto en socios de varios grupos: no puede entrar')
        ->assertDontSee('Dar acceso');
});

it('la fila de un contacto dado de baja ofrece dar acceso si el numero sigue entrando', function () {
    ($this->socio)('C-004872', 'GRP-014', 'Grupo Monasterio');
    ($this->contacto)('p-002', 'C-004872', 'Monica Otra', '70741828');
    ContactoRecord::query()->where('id_de_persona', 'p-001')->update(['dado_de_baja_el' => '2026-10-05 12:00:00']);

    $html = (string) $this->get('/admin/contactos?q=70741828')->getContent();

    expect(($this->fila)($html, 'Monica Salvatierra'))->toContain('Dar acceso');

    $this->post('/admin/contactos/n-59170741828/habilitar');

    expect($this->directorio->estaActivo(IdDePersona::desde('n-59170741828')))->toBeTrue();
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

    $this->post('/admin/contactos/n-59176543210/habilitar')->assertRedirect(route('admin.contactos'));

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
