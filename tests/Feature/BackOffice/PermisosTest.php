<?php

declare(strict_types=1);

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use BackOffice\Infrastructure\Entra\AutenticadorDeDesarrollo;
use BackOffice\Presentation\Http\SesionDeOperador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;

uses(RefreshDatabase::class);

/** @param list<Permiso> $permisos */
function operadorCon(array $permisos): Operador
{
    return new Operador(IdDeOperador::desdeOid('oid-9'), 'Ana Suárez', 'ana@agropartners.com.bo', $permisos);
}

it('sin el permiso de accesos, contactos responde 403', function () {
    SesionDeOperador::guardar(operadorCon([Permiso::Catalogo]));

    $this->get('/admin/contactos')
        ->assertStatus(403)
        ->assertSee('No tenés permiso para esta sección.');
});

it('con el permiso de accesos, contactos abre', function () {
    SesionDeOperador::guardar(operadorCon([Permiso::Accesos]));

    $this->get('/admin/contactos')->assertStatus(200);
});

it('una sesion sin permisos es una sesion vencida', function () {
    // Una sesión abierta antes de que existieran los permisos no puede dar un
    // 500 ni entrar con todo: se vuelve a ingresar.
    Session::put('backoffice.operador', ['oid' => 'x', 'nombre' => 'X', 'correo' => 'x@agropartners.com.bo']);

    expect(SesionDeOperador::actual())->toBeNull();
    $this->get('/admin/contactos')->assertRedirect(route('admin.entrar'));
});

it('un permiso desconocido en la sesion se ignora', function () {
    Session::put('backoffice.operador', [
        'oid' => 'x', 'nombre' => 'X', 'correo' => 'x@agropartners.com.bo',
        'permisos' => ['accesos', 'borrar-todo'],
    ]);

    expect(SesionDeOperador::actual()?->permisos)->toBe([Permiso::Accesos]);
});

it('los autenticadores de hoy dan todos los permisos', function () {
    $autenticador = new AutenticadorDeDesarrollo('local', 'Dev', 'dev@agropartners.com.bo', 'oid-1', 'http://localhost/admin/callback');
    parse_str((string) parse_url($autenticador->urlDeIngreso('estado', 'desafio'), PHP_URL_QUERY), $consulta);

    $operador = $autenticador->resolver((string) $consulta['code'], 'verificador');

    expect($operador->puede(Permiso::Catalogo))->toBeTrue()
        ->and($operador->puede(Permiso::Accesos))->toBeTrue();
});
