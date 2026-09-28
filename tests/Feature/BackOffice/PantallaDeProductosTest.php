<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use BackOffice\Presentation\Http\SesionDeOperador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    SesionDeOperador::guardar(new Operador(IdDeOperador::desdeOid('oid-5'), 'Rosa Vaca', 'rosa@agropartners.com.bo', Permiso::todos()));
    $this->gliforte = CatalogoDeEjemplo::idDe('Gliforte 68 SG');
});

/** Lo que el formulario manda para Gliforte, cambiando solo lo que se pasa. */
function formularioDeGliforte(array $cambios = []): array
{
    return array_merge([
        'imagen_url' => 'https://cdn.agropartners.com.bo/productos/A-0142.webp',
        'ficha_tecnica_url' => 'https://docs.agropartners.com.bo/A-0142-tds.pdf',
        'hoja_seguridad_url' => 'https://docs.agropartners.com.bo/A-0142-msds.pdf',
        'registro_sanitario_url' => '',
    ], $cambios);
}

function asientos(): int
{
    return DB::table('backoffice_cambios_de_catalogo')->count();
}

it('lista todos los productos y dice cuales ve la App', function () {
    $this->get('/admin/productos')
        ->assertOk()
        ->assertSee('Gliforte 68 SG')
        ->assertSee('Sin categoría todavía')
        ->assertSeeInOrder(['Dado de baja', 'No']);
});

it('busca y filtra', function () {
    $this->get('/admin/productos?q=gliforte')->assertSee('Gliforte 68 SG')->assertDontSee('Atrazina 90 WG');
    $this->get('/admin/productos?filtro=sin-imagen')->assertDontSee('Gliforte 68 SG')->assertSee('Atrazina 90 WG');
});

it('el formulario muestra la vista previa y el historial', function () {
    $this->get("/admin/productos/{$this->gliforte}")
        ->assertOk()
        ->assertSee('<img src="https://cdn.agropartners.com.bo/productos/A-0142.webp"', false)
        ->assertSee('value="https://docs.agropartners.com.bo/A-0142-tds.pdf"', false)
        ->assertSee('value="https://docs.agropartners.com.bo/A-0142-msds.pdf"', false)
        ->assertSee('Historial de cambios');
});

it('el formulario explica por que la App no lo ve', function () {
    $this->get('/admin/productos/'.CatalogoDeEjemplo::idDe('Dado de baja'))->assertSee('Está dado de baja.');
});

it('guardar un cambio lo avisa y lo asienta', function () {
    $this->post("/admin/productos/{$this->gliforte}", formularioDeGliforte([
        'ficha_tecnica_url' => 'https://docs.agropartners.com.bo/A-0142-tds-v2.pdf',
    ]))
        ->assertRedirect(route('admin.producto', $this->gliforte))
        ->assertSessionHas('aviso', 'Se guardó 1 cambio.');

    expect(asientos())->toBe(1)
        ->and(DB::table('backoffice_cambios_de_catalogo')->value('operador'))->toBe('Rosa Vaca');
});

it('varios cambios se cuentan en plural', function () {
    $this->post("/admin/productos/{$this->gliforte}", formularioDeGliforte([
        'imagen_url' => '',
        'hoja_seguridad_url' => '',
    ]))->assertSessionHas('aviso', 'Se guardaron 2 cambios.');
});

it('un pdf como imagen vuelve con el error', function () {
    $this->from("/admin/productos/{$this->gliforte}")
        ->post("/admin/productos/{$this->gliforte}", formularioDeGliforte(['imagen_url' => 'https://x.bo/a.pdf']))
        ->assertRedirect("/admin/productos/{$this->gliforte}")
        ->assertSessionHasErrors(['imagen_url' => 'La imagen tiene que ser .jpg, .jpeg, .png o .webp.'])
        ->assertSessionHasInput('imagen_url', 'https://x.bo/a.pdf');

    expect(asientos())->toBe(0)
        ->and(DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte)->value('imagen_url'))
        ->toBe('https://cdn.agropartners.com.bo/productos/A-0142.webp');
});

it('sin cambios lo dice y no asienta', function () {
    $this->post("/admin/productos/{$this->gliforte}", formularioDeGliforte())
        ->assertSessionHas('aviso', 'No había cambios.');

    expect(asientos())->toBe(0);
});

it('se edita un producto sin codigo', function () {
    $sinCodigo = CatalogoDeEjemplo::idDe('Sin código SAP todavía');

    $this->post("/admin/productos/{$sinCodigo}", ['imagen_url' => 'https://cdn.x.bo/nueva.jpg'])
        ->assertSessionHas('aviso', 'Se guardó 1 cambio.');

    expect(DB::table('backoffice_cambios_de_catalogo')->value('item_code'))->toBeNull();
});

it('sin el permiso de catalogo responde 403', function () {
    SesionDeOperador::guardar(new Operador(IdDeOperador::desdeOid('oid-6'), 'Luis Paz', 'luis@agropartners.com.bo', [Permiso::Accesos]));

    $this->get('/admin/productos')->assertStatus(403);
    $this->post("/admin/productos/{$this->gliforte}", formularioDeGliforte(['imagen_url' => '']))->assertStatus(403);

    expect(asientos())->toBe(0);
});

it('un id que no existe es 404', function () {
    $this->get('/admin/productos/999999')->assertNotFound();
    $this->get('/admin/productos/abc')->assertNotFound();
    $this->post('/admin/productos/999999', formularioDeGliforte())->assertNotFound();
});

it('el encabezado muestra solo las secciones permitidas', function () {
    SesionDeOperador::guardar(new Operador(IdDeOperador::desdeOid('oid-7'), 'Eva Rojas', 'eva@agropartners.com.bo', [Permiso::Catalogo]));

    $this->get('/admin/productos')
        ->assertSee(route('admin.productos'), false)
        ->assertDontSee(route('admin.contactos'), false);
});
