<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Ingesta\DarDeBajaProducto\DarDeBajaProducto;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ProductoIngresado;
use Maestros\Application\Ingesta\ReplicarProducto\ReplicarProducto;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Tests\Soporte\CatalogoDeEjemplo;
use Tests\Soporte\ProductoDeIngesta;

uses(RefreshDatabase::class);

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    $this->momento = new DateTimeImmutable('2026-10-20T15:00:00Z');
});

function replicarProducto(ProductoIngresado $p, OperacionDeIngesta $op = OperacionDeIngesta::Crear, ?string $ruta = null): Result
{
    return app(Mediator::class)->send(new ReplicarProducto(
        $op,
        $ruta ?? ($op === OperacionDeIngesta::Reemplazar ? $p->itemCode : null),
        $p,
    ));
}

/** @return array<string, mixed>|null */
function filaDeProducto(string $itemCode): ?array
{
    $fila = DB::table(CatalogoDeEjemplo::tabla('producto'))->where('codigo_articulo', $itemCode)->first();

    return $fila === null ? null : (array) $fila;
}

/** @return list<string> */
function cultivosDeProducto(string $itemCode): array
{
    $id = filaDeProducto($itemCode)['id_producto'] ?? 0;

    return DB::table(CatalogoDeEjemplo::tabla('producto_cultivo'))
        ->where('id_producto', $id)
        ->orderBy('cultivo')
        ->pluck('cultivo')
        ->map(fn ($c): string => (string) $c)
        ->all();
}

it('crear guarda la ficha, la categoria y los cultivos', function () {
    $resultado = replicarProducto(ProductoDeIngesta::ingresado());

    $fila = filaDeProducto('10198');

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and($fila['nombre'])->toBe('ALVO - 1 L')
        ->and($fila['presentacion'])->toBe('1 L')
        ->and($fila['codigo_categoria'])->toBe('insecticidas')
        ->and($fila['formulacion'])->toBe('SUSPENSION CONCENTRADA (SC)')
        ->and($fila['registro_entidad'])->toBe('SENASAG')
        ->and($fila['registro_numero'])->toBe('4848')
        ->and($fila['origen_esquema'])->toBe('AGRO_P6')
        ->and((int) $fila['origen_evento_id'])->toBe(5120)
        ->and($fila['dado_de_baja_el'])->toBeNull()
        ->and(cultivosDeProducto('10198'))->toHaveCount(2);
});

it('crear un producto que existe responde PRODUCTO_YA_EXISTE', function () {
    $resultado = replicarProducto(ProductoDeIngesta::ingresado(['itemCode' => 'A-0142']));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('PRODUCTO_YA_EXISTE');
});

it('crear uno dado de baja lo reactiva si es mas nuevo', function () {
    $resultado = replicarProducto(ProductoDeIngesta::ingresado(['itemCode' => 'A-0300']));

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and(filaDeProducto('A-0300')['dado_de_baja_el'])->toBeNull();
});

it('crear uno dado de baja con un cuerpo mas viejo que la baja lo ignora', function () {
    $resultado = replicarProducto(ProductoDeIngesta::ingresado([
        'itemCode' => 'A-0300',
        'vigenteDesde' => '2026-10-09T12:00:00Z',
    ]));

    expect($resultado->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(filaDeProducto('A-0300')['dado_de_baja_el'])->not->toBeNull();
});

it('reemplazar uno inexistente o dado de baja responde PRODUCTO_NO_ENCONTRADO', function (string $itemCode) {
    $resultado = replicarProducto(
        ProductoDeIngesta::ingresado(['itemCode' => $itemCode]),
        OperacionDeIngesta::Reemplazar,
    );

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('PRODUCTO_NO_ENCONTRADO');
})->with(['inexistente' => '99999', 'dado de baja' => 'A-0300']);

it('reemplazar con otro itemCode responde ITEMCODE_NO_COINCIDE y no escribe nada', function () {
    $resultado = replicarProducto(
        ProductoDeIngesta::ingresado(),
        OperacionDeIngesta::Reemplazar,
        'A-0142',
    );

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('ITEMCODE_NO_COINCIDE')
        ->and(filaDeProducto('10198'))->toBeNull();
});

it('reemplazar conserva la descripcion y los cuatro enlaces', function () {
    replicarProducto(
        ProductoDeIngesta::ingresado(['itemCode' => 'A-0142', 'nombre' => 'GLIFORTE 68 SG - 10 KG']),
        OperacionDeIngesta::Reemplazar,
    );

    $fila = filaDeProducto('A-0142');

    expect($fila['nombre'])->toBe('GLIFORTE 68 SG - 10 KG')
        ->and($fila['descripcion'])->toBe('Herbicida sistémico no selectivo de amplio espectro.')
        ->and($fila['imagen_url'])->toBe('https://cdn.agropartners.com.bo/productos/A-0142.webp')
        ->and($fila['ficha_tecnica_url'])->toBe('https://docs.agropartners.com.bo/A-0142-tds.pdf')
        ->and($fila['hoja_seguridad_url'])->toBe('https://docs.agropartners.com.bo/A-0142-msds.pdf')
        ->and($fila['registro_sanitario_url'])->toBeNull();
});

/**
 * P9: los campos de usuario de SAP suelen llegar vacíos, y así no borran la
 * ficha que cargó la web hasta que se concilien los datos.
 */
it('un dato vacio no borra nada', function () {
    replicarProducto(
        ProductoDeIngesta::ingresado([
            'itemCode' => 'A-0142',
            'presentacion' => null,
            'ingredienteActivo' => null,
            'formulacionCodigo' => null,
            'formulacionDescripcion' => null,
            'dosisReferencial' => null,
            'categoria' => null,
            'registro' => null,
            'cultivos' => [],
        ]),
        OperacionDeIngesta::Reemplazar,
    );

    $fila = filaDeProducto('A-0142');

    expect($fila['presentacion'])->toBe('Bolsa 10 Kg')
        ->and($fila['ingrediente_activo'])->toBe('Glifosato sal de amonio 68%')
        ->and($fila['formulacion'])->toBe('Gránulos solubles (SG)')
        ->and($fila['dosis_referencial'])->toBe('1,5 – 3,0 Kg/ha')
        ->and($fila['registro_numero'])->toBe('1842-H')
        ->and($fila['codigo_categoria'])->toBe('herbicidas')
        ->and(cultivosDeProducto('A-0142'))->toHaveCount(2);
});

it('un cuerpo igual o mas viejo que lo guardado se ignora entero', function () {
    replicarProducto(ProductoDeIngesta::ingresado());

    $mismoInstante = replicarProducto(
        ProductoDeIngesta::ingresado(['nombre' => 'OTRO']),
        OperacionDeIngesta::Reemplazar,
    );

    expect($mismoInstante->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(filaDeProducto('10198')['nombre'])->toBe('ALVO - 1 L');

    // El mismo segundo con fracción tampoco reemplaza: lo guardado está
    // truncado al segundo y empatar es no reemplazar.
    $mismoSegundo = replicarProducto(
        ProductoDeIngesta::ingresado(['nombre' => 'OTRO', 'vigenteDesde' => '2026-10-16T12:00:00.900Z']),
        OperacionDeIngesta::Reemplazar,
    );

    expect($mismoSegundo->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(filaDeProducto('10198')['nombre'])->toBe('ALVO - 1 L');
});

it('un producto de la web acepta cualquier vigencia', function () {
    $resultado = replicarProducto(
        ProductoDeIngesta::ingresado(['itemCode' => 'A-0142', 'vigenteDesde' => '2001-01-01T00:00:00Z']),
        OperacionDeIngesta::Reemplazar,
    );

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado);
});

it('una categoria nueva se crea con el nombre de SAP y una existente no cambia de nombre', function () {
    replicarProducto(ProductoDeIngesta::ingresado([
        'itemCode' => 'SEM-01',
        'categoria' => ['codigo' => 'tratamiento-de-semillas', 'nombre' => 'TRATAMIENTO DE SEMILLAS'],
    ]));

    expect(DB::table(CatalogoDeEjemplo::tabla('categoria'))->where('codigo', 'tratamiento-de-semillas')->value('nombre'))
        ->toBe('TRATAMIENTO DE SEMILLAS');

    replicarProducto(ProductoDeIngesta::ingresado([
        'categoria' => ['codigo' => ' INSECTICIDAS', 'nombre' => 'INSECTICIDAS'],
    ]));

    expect(DB::table(CatalogoDeEjemplo::tabla('categoria'))->where('codigo', 'insecticidas')->value('nombre'))->toBe('Insecticidas')
        ->and(DB::table(CatalogoDeEjemplo::tabla('categoria'))->count())->toBe(4)
        ->and(filaDeProducto('10198')['codigo_categoria'])->toBe('insecticidas');
});

it('cultivos repetidos por mayusculas dan una sola fila', function () {
    replicarProducto(ProductoDeIngesta::ingresado(['cultivos' => ['SOYA', 'Soya']]));

    expect(cultivosDeProducto('10198'))->toBe(['SOYA']);
});

it('dar de baja fija la baja y la vigencia en el momento y conserva la ficha', function () {
    $resultado = app(Mediator::class)->send(new DarDeBajaProducto('A-0142', $this->momento));

    $fila = filaDeProducto('A-0142');

    expect($resultado->isSuccess)->toBeTrue()
        ->and((string) $fila['dado_de_baja_el'])->toStartWith('2026-10-20 15:00:00')
        ->and((string) $fila['vigente_desde'])->toStartWith('2026-10-20 15:00:00')
        ->and($fila['descripcion'])->toBe('Herbicida sistémico no selectivo de amplio espectro.')
        ->and($fila['imagen_url'])->toBe('https://cdn.agropartners.com.bo/productos/A-0142.webp');
});

it('dar de baja uno inexistente o ya dado de baja responde PRODUCTO_NO_ENCONTRADO', function (string $itemCode) {
    $resultado = app(Mediator::class)->send(new DarDeBajaProducto($itemCode, test()->momento));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('PRODUCTO_NO_ENCONTRADO');
})->with(['inexistente' => '99999', 'ya dado de baja' => 'A-0300']);
