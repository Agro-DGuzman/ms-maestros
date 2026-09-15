<?php

declare(strict_types=1);

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Presentation\Http\SesionDeOperador;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;

uses(RefreshDatabase::class);

beforeEach(function () {
    SesionDeOperador::guardar(new Operador(
        IdDeOperador::desdeOid('oid-77'),
        'Jorge Pena',
        'jorge@agropartners.com.bo',
    ));
});

it('muestra quien dio y quien quito el acceso', function () {
    $bitacora = app(BitacoraRepository::class);

    $bitacora->asentar(AsientoDeBitacora::nuevo(
        new Operador(IdDeOperador::desdeOid('oid-1'), 'Monica Salvatierra', 'm@a.bo'),
        'p-001', AccionDeAcceso::Concedio, '190.129.4.7',
        new DateTimeImmutable('2026-09-10T10:00:00+00:00'),
    ));
    $bitacora->asentar(AsientoDeBitacora::nuevo(
        new Operador(IdDeOperador::desdeOid('oid-2'), 'Ana Roca', 'a@a.bo'),
        'p-001', AccionDeAcceso::Revoco, '190.129.4.8',
        new DateTimeImmutable('2026-09-12T10:00:00+00:00'),
    ));

    $this->get('/admin/contactos/p-001/historial')
        ->assertOk()
        ->assertSee('Monica Salvatierra')
        ->assertSee('Ana Roca')
        ->assertSee('concedió el acceso', false)
        ->assertSee('quitó el acceso', false)
        ->assertSee('190.129.4.7');
});

it('una persona sin movimientos lo dice', function () {
    $this->get('/admin/contactos/p-999/historial')
        ->assertOk()
        ->assertSee('Todavía no hay movimientos', false);
});

it('el historial no filtra la credencial de la persona', function () {
    // El §4.2 dice que la contrasena generada no se muestra nunca, ni al
    // crearla. Lo que hay que comprobar es que no aparezca el secreto, no que
    // no aparezca la palabra: la propia pantalla explica que no lo muestra.
    $persona = IdDePersona::desde('p-777');
    app(BovedaDeContrasenas::class)->guardar($persona, 'una-credencial-secretisima');

    $this->get('/admin/contactos/p-777/historial')
        ->assertOk()
        ->assertDontSee('una-credencial-secretisima');
});

it('el historial de una persona no muestra los movimientos de otra', function () {
    app(BitacoraRepository::class)->asentar(AsientoDeBitacora::nuevo(
        new Operador(IdDeOperador::desdeOid('oid-9'), 'Otro Operador', 'o@a.bo'),
        'p-002', AccionDeAcceso::Concedio, '10.0.0.1',
        new DateTimeImmutable('2026-09-10T10:00:00+00:00'),
    ));

    $this->get('/admin/contactos/p-001/historial')
        ->assertOk()
        ->assertDontSee('Otro Operador');
});
