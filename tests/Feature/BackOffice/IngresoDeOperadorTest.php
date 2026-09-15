<?php

declare(strict_types=1);

use BackOffice\Presentation\Http\SesionDeOperador;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('entrar redirige al autenticador', function () {
    $this->get('/admin/entrar')->assertRedirect();
});

it('entrar guarda el estado y el verificador en la sesion', function () {
    $this->get('/admin/entrar')
        ->assertSessionHas('backoffice.estado')
        ->assertSessionHas('backoffice.verificador');
});

it('el callback con el codigo correcto abre la sesion', function () {
    $this->get('/admin/entrar');
    $estado = session('backoffice.estado');

    $this->get("/admin/callback?code=desarrollo&state={$estado}")
        ->assertRedirect(route('admin.contactos'));

    expect(SesionDeOperador::actual())->not->toBeNull()
        ->and(SesionDeOperador::actual()->nombre)->toBe('Operador Local');
});

it('el callback con un estado distinto no abre sesion', function () {
    // Control de CSRF del flujo OAuth: un callback cuyo `state` no es el que
    // emitimos no abre nada.
    $this->get('/admin/entrar');

    $this->get('/admin/callback?code=desarrollo&state=otro-estado')
        ->assertRedirect(route('admin.entrar'));

    expect(SesionDeOperador::actual())->toBeNull();
});

it('el callback con un codigo que no emitimos no abre sesion', function () {
    $this->get('/admin/entrar');
    $estado = session('backoffice.estado');

    $this->get("/admin/callback?code=inventado&state={$estado}")
        ->assertRedirect(route('admin.entrar'));

    expect(SesionDeOperador::actual())->toBeNull();
});

it('sin sesion la lista manda a entrar', function () {
    $this->get('/admin/contactos')->assertRedirect(route('admin.entrar'));
});

it('salir borra la sesion', function () {
    $this->get('/admin/entrar');
    $this->get('/admin/callback?code=desarrollo&state='.session('backoffice.estado'));

    $this->post('/admin/salir')->assertRedirect(route('admin.entrar'));

    expect(SesionDeOperador::actual())->toBeNull();
});

it('las rutas del back-office no se mezclan con el contrato publico', function () {
    $publicas = collect(Route::getRoutes())
        ->map(fn ($r): string => $r->uri())
        ->filter(fn (string $uri): bool => str_starts_with($uri, 'v1/'));

    expect($publicas->filter(fn (string $uri): bool => str_contains($uri, 'admin')))->toBeEmpty();
});
