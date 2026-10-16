<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\CatalogoDeEjemplo;
use Tests\Soporte\Contrato;
use Tests\Soporte\Ingesta;
use Tests\Soporte\ProductoDeIngesta;
use Tests\Soporte\TokenDeEntra;

uses(RefreshDatabase::class);

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    TokenDeEntra::configurar();
});

function crearProductoPorIngesta(array $cuerpo = [], ?array $cabeceras = null): TestResponse
{
    $respuesta = test()->postJson('/ingesta/v1/productos', ProductoDeIngesta::cuerpo($cuerpo), $cabeceras ?? Ingesta::cabeceras());

    expect(Contrato::diferencias($respuesta, 'POST', '/productos', 'ingesta-api-v1.yaml'))->toBe([]);

    return $respuesta;
}

function reemplazarProductoPorIngesta(string $itemCode, array $cuerpo = [], ?array $cabeceras = null): TestResponse
{
    $respuesta = test()->putJson(
        "/ingesta/v1/productos/{$itemCode}",
        ProductoDeIngesta::cuerpo($cuerpo),
        $cabeceras ?? Ingesta::cabeceras(),
    );

    expect(Contrato::diferencias($respuesta, 'PUT', '/productos/{itemCode}', 'ingesta-api-v1.yaml'))->toBe([]);

    return $respuesta;
}

function darDeBajaProductoPorIngesta(string $itemCode, ?array $cabeceras = null): TestResponse
{
    $respuesta = test()->deleteJson("/ingesta/v1/productos/{$itemCode}", [], $cabeceras ?? Ingesta::cabeceras());

    expect(Contrato::diferencias($respuesta, 'DELETE', '/productos/{itemCode}', 'ingesta-api-v1.yaml'))->toBe([]);

    return $respuesta;
}

/** @return array<string, mixed> */
function columnasDelProducto(string $itemCode): array
{
    return (array) DB::table(CatalogoDeEjemplo::tabla('producto'))->where('codigo_articulo', $itemCode)->first();
}

it('POST crea el producto y responde 201 con su itemCode', function () {
    crearProductoPorIngesta()->assertStatus(201)->assertJsonPath('data.itemCode', '10198');
});

it('POST de un producto que existe responde 409 PRODUCTO_YA_EXISTE', function () {
    crearProductoPorIngesta(['itemCode' => 'A-0142'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', ['PRODUCTO_YA_EXISTE']);
});

/** El reintento del Sincronizador: el POST da 409 y el PUT con la misma clave aplica. */
it('POST y despues PUT con la misma clave aplican el PUT', function () {
    $clave = str_pad('a1', 32, '0', STR_PAD_LEFT);

    crearProductoPorIngesta(['itemCode' => 'A-0142'], Ingesta::cabeceras($clave))->assertStatus(409);

    reemplazarProductoPorIngesta('A-0142', ['itemCode' => 'A-0142', 'nombre' => 'GLIFORTE 68 SG'], Ingesta::cabeceras($clave))
        ->assertStatus(200);

    expect(columnasDelProducto('A-0142')['nombre'])->toBe('GLIFORTE 68 SG');
});

it('PUT con otro itemCode en la ruta responde 422 ITEMCODE_NO_COINCIDE', function () {
    reemplazarProductoPorIngesta('A-0142')
        ->assertStatus(422)
        ->assertJsonPath('error.code', ['ITEMCODE_NO_COINCIDE']);
});

it('PUT de un producto inexistente responde 404 PRODUCTO_NO_ENCONTRADO', function () {
    reemplazarProductoPorIngesta('99999', ['itemCode' => '99999'])
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['PRODUCTO_NO_ENCONTRADO']);
});

it('DELETE responde 204 sin cuerpo, y otra vez 404', function () {
    darDeBajaProductoPorIngesta('A-0142')->assertStatus(204)->assertNoContent();

    darDeBajaProductoPorIngesta('A-0142')
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['PRODUCTO_NO_ENCONTRADO']);
});

it('un cuerpo mal formado responde 400 con un FieldError por campo', function (array $cuerpo, string $campo) {
    $respuesta = crearProductoPorIngesta($cuerpo)->assertStatus(400);

    expect(array_column((array) $respuesta->json('error.structuredMessage'), 'campo'))->toContain($campo);
})->with([
    'falta el nombre' => [['nombre' => null], 'nombre'],
    'activo no es booleano' => [['activo' => 'si'], 'activo'],
    'categoria sin codigo' => [['categoria' => ['nombre' => 'INSECTICIDAS']], 'categoria.codigo'],
    'otra entidad de registro' => [['registro' => ['entidad' => 'OTRA', 'numero' => '1']], 'registro.entidad'],
    'sin cultivos' => [['cultivos' => null], 'cultivos'],
    'itemCode demasiado largo' => [['itemCode' => str_repeat('a', 51)], 'itemCode'],
    'presentacion demasiado larga' => [['presentacion' => str_repeat('a', 256)], 'presentacion'],
]);

/** P5: la garantía la cumple ms-maestros, no la confianza en el cuerpo. */
it('los campos que no estan en el contrato se ignoran', function () {
    reemplazarProductoPorIngesta('A-0142', [
        'itemCode' => 'A-0142',
        'descripcion' => 'Pisada',
        'imagenUrl' => 'https://x/y.webp',
    ])->assertStatus(200);

    $fila = columnasDelProducto('A-0142');

    expect($fila['descripcion'])->toBe('Herbicida sistémico no selectivo de amplio espectro.')
        ->and($fila['imagen_url'])->toBe('https://cdn.agropartners.com.bo/productos/A-0142.webp');
});

/** P9: los campos de usuario de SAP suelen llegar como cadena vacía. */
it('textos vacios o con espacios no pisan la ficha', function () {
    reemplazarProductoPorIngesta('A-0142', [
        'itemCode' => 'A-0142',
        'presentacion' => '   ',
        'dosisReferencial' => '',
        'cultivos' => ['  '],
    ])->assertStatus(200);

    $fila = columnasDelProducto('A-0142');
    $cultivos = DB::table(CatalogoDeEjemplo::tabla('producto_cultivo'))->where('id_producto', $fila['id_producto'])->count();

    expect($fila['presentacion'])->toBe('Bolsa 10 Kg')
        ->and($fila['dosis_referencial'])->toBe('1,5 – 3,0 Kg/ha')
        ->and($cultivos)->toBe(2);
});

it('la vigencia del Sincronizador se pasa a UTC y se compara al segundo', function () {
    crearProductoPorIngesta(['vigenteDesde' => '2026-10-15T19:32:58.866982+00:00'])->assertStatus(201);

    expect((string) columnasDelProducto('10198')['vigente_desde'])->toStartWith('2026-10-15 19:32:58');

    // El mismo instante escrito con desfase: no reemplaza.
    reemplazarProductoPorIngesta('10198', [
        'nombre' => 'OTRO',
        'vigenteDesde' => '2026-10-15T15:32:58-04:00',
    ])->assertStatus(200);

    expect(columnasDelProducto('10198')['nombre'])->toBe('ALVO - 1 L');
});

it('inactivo sale de la App y reactivado vuelve con sus enlaces', function () {
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
    $sesion = ['Authorization' => 'Bearer token-bueno'];

    reemplazarProductoPorIngesta('A-0142', [
        'itemCode' => 'A-0142',
        'activo' => false,
        'vigenteDesde' => '2026-10-17T12:00:00Z',
    ])->assertStatus(200);

    $this->getJson('/v1/productos/A-0142', $sesion)->assertStatus(404);

    reemplazarProductoPorIngesta('A-0142', [
        'itemCode' => 'A-0142',
        'activo' => true,
        'vigenteDesde' => '2026-10-18T12:00:00Z',
    ])->assertStatus(200);

    $this->getJson('/v1/productos/A-0142', $sesion)
        ->assertStatus(200)
        ->assertJsonPath('data.imagenUrl', 'https://cdn.agropartners.com.bo/productos/A-0142.webp');
});

it('cada pedido deja su linea en el log', function () {
    Log::spy();

    crearProductoPorIngesta();

    Log::shouldHaveReceived('info')->withArgs(
        fn (string $mensaje, array $contexto = []): bool => $mensaje === 'ingesta'
            && $contexto['itemCode'] === '10198'
            && $contexto['operacion'] === 'crear'
            && $contexto['resultado'] === 'aplicado'
            && $contexto['eventoId'] === 5120,
    );
});
