<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Maestros\Infrastructure\Persistence\SocioRecord;
use Tests\Soporte\Contrato;
use Tests\Soporte\Ingesta;
use Tests\Soporte\TokenDeEntra;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    TokenDeEntra::configurar();
});

/** Cada respuesta tiene que cumplir el contrato de ingesta, sea cual sea su status. */
function cumpleElContratoDeIngesta(TestResponse $respuesta, string $metodo, string $ruta): TestResponse
{
    expect(Contrato::diferencias($respuesta, $metodo, $ruta, 'ingesta-api-v1.yaml'))->toBe([]);

    return $respuesta;
}

function crearSocio(array $cuerpo = [], ?array $cabeceras = null): TestResponse
{
    $respuesta = test()->postJson('/ingesta/v1/socios', Ingesta::cuerpo($cuerpo), $cabeceras ?? Ingesta::cabeceras());

    return cumpleElContratoDeIngesta($respuesta, 'POST', '/socios');
}

function reemplazarSocio(string $cardCode, array $cuerpo = [], ?array $cabeceras = null): TestResponse
{
    $respuesta = test()->putJson("/ingesta/v1/socios/{$cardCode}", Ingesta::cuerpo($cuerpo), $cabeceras ?? Ingesta::cabeceras());

    return cumpleElContratoDeIngesta($respuesta, 'PUT', '/socios/{cardCode}');
}

it('POST crea el socio y responde 201 con su cardCode', function () {
    crearSocio()->assertStatus(201)->assertJsonPath('data.cardCode', 'C-900001');
});

it('POST de un socio que ya existe responde 409', function () {
    crearSocio();

    crearSocio()->assertStatus(409)->assertJsonPath('error.code', ['SOCIO_YA_EXISTE']);
});

it('PUT con vigencia posterior responde 200', function () {
    crearSocio();

    reemplazarSocio('C-900001', ['vigenteDesde' => '2026-10-05T13:00:00Z'])->assertStatus(200);
});

it('PUT de un socio inexistente responde 404', function () {
    reemplazarSocio('C-999999', ['cardCode' => 'C-999999'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['SOCIO_NO_ENCONTRADO']);
});

it('PUT con otro cardCode en el cuerpo responde 422', function () {
    crearSocio();

    reemplazarSocio('C-900001', ['cardCode' => 'C-900002'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', ['CARDCODE_NO_COINCIDE']);
});

it('un cuerpo mal formado responde 400 con el campo', function (array $cuerpo, string $campo) {
    $respuesta = crearSocio($cuerpo)->assertStatus(400);

    expect(array_column((array) $respuesta->json('error.structuredMessage'), 'campo'))->toContain($campo);
})->with([
    'sin razon social' => [['razonSocial' => null], 'razonSocial'],
    // SAP siempre trae OCPR.Name: un nombre vacío es un dato corrupto, y en
    // la pantalla de Contactos rompería el cálculo de las iniciales.
    'contacto sin nombre' => [['contactos' => [['codigoDeContacto' => '1523', 'nombre' => '   ', 'celular' => '70741828', 'activo' => true]]], 'contactos.0.nombre'],
    'codigo de contacto repetido' => [['contactos' => [
        ['codigoDeContacto' => '1523', 'nombre' => 'Mónica', 'celular' => '70741828', 'activo' => true],
        ['codigoDeContacto' => '1523', 'nombre' => 'Otra', 'celular' => '70112233', 'activo' => true],
    ]], 'contactos.1.codigoDeContacto'],
    'codigo de contacto con letras' => [['contactos' => [['codigoDeContacto' => '15a3', 'nombre' => 'Mónica', 'celular' => '70741828', 'activo' => true]]], 'contactos.0.codigoDeContacto'],
    'tipo SAP desconocido' => [['tipoSap' => 'X'], 'tipoSap'],
    'grupo sin codigo' => [['grupoEconomico' => ['nombre' => 'Sin codigo']], 'grupoEconomico.codigo'],
]);

it('sin Accept: application/json igual responde el envelope 400, no una redireccion', function () {
    $respuesta = test()->call('POST', '/ingesta/v1/socios', [], [], [], array_merge(
        ['CONTENT_TYPE' => 'application/json'],
        collect(Ingesta::cabeceras())->mapWithKeys(fn ($v, $k) => ['HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all(),
    ), (string) json_encode(Ingesta::cuerpo(['razonSocial' => null])));

    $respuesta->assertStatus(400)->assertJsonPath('success', false);
});

it('un grupo nulo se acepta y el socio queda sin grupo', function () {
    crearSocio(['grupoEconomico' => null])->assertStatus(201);
});

it('DELETE da de baja y responde 204 sin cuerpo; repetido con otra clave, 404', function () {
    crearSocio();

    $baja = test()->deleteJson('/ingesta/v1/socios/C-900001', [], Ingesta::cabeceras());
    $baja->assertStatus(204);
    expect($baja->getContent())->toBe('');

    cumpleElContratoDeIngesta(
        test()->deleteJson('/ingesta/v1/socios/C-900001', [], Ingesta::cabeceras()),
        'DELETE',
        '/socios/{cardCode}',
    )->assertStatus(404);
});

it('sin el secreto del gateway responde 403, y sin token 401', function () {
    crearSocio(cabeceras: Ingesta::cabeceras(sin: ['X-Gateway-Secret']))->assertStatus(403);
    crearSocio(cabeceras: Ingesta::cabeceras(sin: ['Authorization']))->assertStatus(401);
});

it('un POST que dio 409 se reintenta como PUT con la misma clave y se aplica', function () {
    // Es exactamente lo que hace el Sincronizador: la terna distingue al PUT.
    crearSocio();
    $clave = str_repeat('e', 32);

    crearSocio(['vigenteDesde' => '2026-10-05T13:00:00Z'], Ingesta::cabeceras($clave))->assertStatus(409);
    reemplazarSocio('C-900001', ['vigenteDesde' => '2026-10-05T13:00:00Z', 'razonSocial' => 'Agro Prueba Renombrada'], Ingesta::cabeceras($clave))
        ->assertStatus(200);

    expect(SocioRecord::query()->find('C-900001')?->razon_social)->toBe('Agro Prueba Renombrada');
});

it('deja una linea de log por envio, para cruzarla con la del Sincronizador', function () {
    Log::spy();

    crearSocio();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $mensaje, array $contexto = []): bool => $mensaje === 'ingesta'
            && $contexto['cardCode'] === 'C-900001'
            && $contexto['operacion'] === 'crear'
            && $contexto['resultado'] === 'aplicado'
            && $contexto['eventoId'] === 1842,
    );
});
