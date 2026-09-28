<?php

declare(strict_types=1);

use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
});

const CON_SESION = ['Authorization' => 'Bearer token-bueno'];

/** @return list<string> */
function codigosListados(string $url): array
{
    /** @var list<array{itemCode: string}> $items */
    $items = test()->getJson($url, CON_SESION)->assertStatus(200)->json('data.items');

    return array_column($items, 'itemCode');
}

it('lista solo lo que la App puede ver, por nombre', function () {
    // Dado de baja, sin código SAP y sin categoría quedan afuera: el contrato
    // pide cada tarjeta con itemCode y categoría, y solo artículos activos.
    expect(codigosListados('/v1/productos'))->toBe(['A-0219', 'A-0142', 'S-0101']);
});

it('filtra por categoria', function () {
    expect(codigosListados('/v1/productos?categoria=herbicidas'))->toBe(['A-0219', 'A-0142']);
});

it('pagina con los metadatos del contrato', function () {
    $respuesta = $this->getJson('/v1/productos?tamanoPagina=1&pagina=2', CON_SESION)->assertStatus(200);

    expect(array_column($respuesta->json('data.items'), 'itemCode'))->toBe(['A-0142'])
        ->and($respuesta->json('data.paginacion'))->toBe(['pagina' => 2, 'tamanoPagina' => 1, 'total' => 3, 'totalPaginas' => 3]);
});

it('la tarjeta lleva la categoria con su nombre y omite lo que no hay', function () {
    $respuesta = $this->getJson('/v1/productos?categoria=herbicidas', CON_SESION);

    expect($respuesta->json('data.items.1'))->toBe([
        'itemCode' => 'A-0142',
        'nombre' => 'Gliforte 68 SG',
        'categoria' => ['codigo' => 'herbicidas', 'nombre' => 'Herbicidas'],
        'presentacion' => 'Bolsa 10 Kg',
        'imagenUrl' => 'https://cdn.agropartners.com.bo/productos/A-0142.webp',
    ])
        // `presentacion` no admite null en el contrato: si no hay, no va.
        ->and($respuesta->json('data.items.0'))->not->toHaveKey('presentacion')
        ->and($respuesta->json('data.items.0.imagenUrl'))->toBeNull();
});

it('rechaza parametros fuera del contrato', function (string $consulta, string $campo) {
    $this->getJson('/v1/productos?'.$consulta, CON_SESION)
        ->assertStatus(400)
        ->assertJsonPath('error.structuredMessage.0.campo', $campo);
})->with([
    'categoria inexistente' => ['categoria=repuestos', 'categoria'],
    'pagina cero' => ['pagina=0', 'pagina'],
    'pagina demasiado grande' => ['tamanoPagina=101', 'tamanoPagina'],
]);

it('la ficha trae todo lo del producto', function () {
    $respuesta = $this->getJson('/v1/productos/A-0142', CON_SESION)->assertStatus(200);

    expect($respuesta->json('data.ingredienteActivo'))->toBe('Glifosato sal de amonio 68%')
        ->and($respuesta->json('data.dosisReferencial'))->toBe('1,5 – 3,0 Kg/ha')
        ->and($respuesta->json('data.cultivos'))->toBe(['Barbecho químico', 'Soja'])
        ->and($respuesta->json('data.registro'))->toBe(['entidad' => 'SENASAG', 'numero' => '1842-H']);
});

it('una ficha sin registro ni cultivos los devuelve vacios, no ausentes', function () {
    $respuesta = $this->getJson('/v1/productos/A-0219', CON_SESION)->assertStatus(200);

    expect($respuesta->json('data'))->toHaveKey('registro')
        ->and($respuesta->json('data.registro'))->toBeNull()
        ->and($respuesta->json('data.cultivos'))->toBe([]);
});

it('lo que la App no puede ver no existe para ella', function (string $itemCode) {
    // Un producto dado de baja responde igual que uno que nunca existió.
    $this->getJson("/v1/productos/{$itemCode}", CON_SESION)
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['RECURSO_NO_ENCONTRADO']);
})->with(['A-0300', 'A-0400', 'A-9999']);

it('los documentos salen de las columnas que tienen URL', function () {
    $respuesta = $this->getJson('/v1/productos/A-0142/documentos', CON_SESION)->assertStatus(200);

    expect($respuesta->json('data.items'))->toBe([
        ['tipo' => 'ficha_tecnica', 'nombre' => 'Ficha técnica · Gliforte 68 SG', 'url' => 'https://docs.agropartners.com.bo/A-0142-tds.pdf'],
        ['tipo' => 'hoja_seguridad', 'nombre' => 'Hoja de seguridad · Gliforte 68 SG', 'url' => 'https://docs.agropartners.com.bo/A-0142-msds.pdf'],
    ])
        ->and(array_column($this->getJson('/v1/productos/S-0101/documentos', CON_SESION)->json('data.items'), 'tipo'))
        ->toBe(['registro_sanitario']);
});

it('sin documentos, o sin producto visible, los documentos responden 404', function (string $itemCode) {
    // Así lo dice el contrato: «no existe, o no tiene documentación técnica
    // cargada». El dado de baja tiene ficha, y aun así no se entrega.
    $this->getJson("/v1/productos/{$itemCode}/documentos", CON_SESION)
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['RECURSO_NO_ENCONTRADO']);
})->with(['A-0219', 'A-0300', 'A-9999']);

it('las categorias son solo las que tienen algo que mostrar, por nombre', function () {
    // Un chip que al tocarlo deja la pantalla vacía no le sirve a nadie:
    // `insecticidas` existe pero no tiene productos.
    $respuesta = $this->getJson('/v1/categorias', CON_SESION)->assertStatus(200);

    expect(array_column($respuesta->json('data.items'), 'codigo'))->toBe(['herbicidas', 'semillas'])
        ->and($respuesta->headers->get('ETag'))->not->toBeNull();
});

it('una categoria sin productos visibles tampoco aparece', function () {
    // Solo un producto dado de baja no alcanza para mostrar el chip.
    DB::table(CatalogoDeEjemplo::tabla('categoria'))->insert(['codigo' => 'fungicidas', 'nombre' => 'Fungicidas']);
    DB::table(CatalogoDeEjemplo::tabla('producto'))->insert([
        'codigo_articulo' => 'F-0001', 'nombre' => 'Kuprex', 'codigo_categoria' => 'fungicidas', 'activo' => false,
    ]);

    expect(array_column($this->getJson('/v1/categorias', CON_SESION)->json('data.items'), 'codigo'))
        ->toBe(['herbicidas', 'semillas']);
});

it('las categorias no son una lista fija: salen de los datos', function () {
    // El contrato enumera cuatro, pero el catálogo real tiene más.
    DB::table(CatalogoDeEjemplo::tabla('categoria'))->insert(['codigo' => 'fungicidas', 'nombre' => 'Fungicidas']);
    DB::table(CatalogoDeEjemplo::tabla('producto'))->insert([
        'codigo_articulo' => 'F-0002', 'nombre' => 'Mancoparts', 'codigo_categoria' => 'fungicidas',
    ]);

    expect(array_column($this->getJson('/v1/categorias', CON_SESION)->json('data.items'), 'codigo'))
        ->toBe(['fungicidas', 'herbicidas', 'semillas'])
        ->and(codigosListados('/v1/productos?categoria=fungicidas'))->toBe(['F-0002']);
});

it('filtrar por una categoria que existe pero no tiene productos es una lista vacia, no un error', function () {
    $respuesta = $this->getJson('/v1/productos?categoria=insecticidas', CON_SESION)->assertStatus(200);

    expect($respuesta->json('data.items'))->toBe([])
        ->and($respuesta->json('data.paginacion.total'))->toBe(0);
});

it('las categorias responden 304 si la App ya las tiene', function () {
    $etag = (string) $this->getJson('/v1/categorias', CON_SESION)->headers->get('ETag');

    expect($this->getJson('/v1/categorias', CON_SESION + ['If-None-Match' => $etag])->assertStatus(304)->getContent())
        ->toBe('');
});

it('todo el catalogo pide token', function (string $ruta) {
    $this->getJson($ruta)->assertStatus(401)->assertJsonPath('error.code', ['TOKEN_INVALIDO']);
})->with(['/v1/productos', '/v1/productos/A-0142', '/v1/productos/A-0142/documentos', '/v1/categorias']);
