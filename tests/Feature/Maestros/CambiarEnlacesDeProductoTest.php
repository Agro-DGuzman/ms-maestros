<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\FieldError;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Core\Results\ValidationError;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProducto;
use Maestros\Application\Productos\CampoDeEnlace;
use Maestros\Application\Productos\EnlacesCambiados;
use Tests\Dobles\VerificadorFalso;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

const IMAGEN_DE_GLIFORTE = 'https://cdn.agropartners.com.bo/productos/A-0142.webp';
const FICHA_DE_GLIFORTE = 'https://docs.agropartners.com.bo/A-0142-tds.pdf';
const HOJA_DE_GLIFORTE = 'https://docs.agropartners.com.bo/A-0142-msds.pdf';

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    $this->app->instance(VerificadorDeToken::class, new VerificadorFalso('p-8f2b1c40'));
    $this->gliforte = CatalogoDeEjemplo::idDe('Gliforte 68 SG');
});

/** Los cuatro enlaces de Gliforte, cambiando solo los que se pasan. */
function cambiarGliforte(array $cambios = []): Result
{
    $actuales = [
        'imagen' => IMAGEN_DE_GLIFORTE,
        'fichaTecnica' => FICHA_DE_GLIFORTE,
        'hojaDeSeguridad' => HOJA_DE_GLIFORTE,
        'registroSanitario' => null,
    ];

    return app(Mediator::class)->send(new CambiarEnlacesDeProducto(test()->gliforte, ...array_merge($actuales, $cambios)));
}

function cambiadosDe(Result $resultado): EnlacesCambiados
{
    expect($resultado->isSuccess)->toBeTrue();
    assert($resultado instanceof ResultWithValue);
    $valor = $resultado->value();
    assert($valor instanceof EnlacesCambiados);

    return $valor;
}

it('guarda y la API lo devuelve', function () {
    cambiarGliforte(['imagen' => 'https://cdn.agropartners.com.bo/productos/A-0142-nueva.png']);

    $this->getJson('/v1/productos/A-0142', ['Authorization' => 'Bearer token-bueno'])
        ->assertJsonPath('data.imagenUrl', 'https://cdn.agropartners.com.bo/productos/A-0142-nueva.png');
});

it('devuelve solo lo que cambio, con el anterior de la base', function () {
    $cambiados = cambiadosDe(cambiarGliforte(['fichaTecnica' => 'https://docs.agropartners.com.bo/A-0142-tds-v2.pdf']));

    expect($cambiados->itemCode)->toBe('A-0142')
        ->and($cambiados->cambios)->toHaveCount(1)
        ->and($cambiados->cambios[0]->campo)->toBe(CampoDeEnlace::FichaTecnica)
        ->and($cambiados->cambios[0]->anterior)->toBe(FICHA_DE_GLIFORTE)
        ->and($cambiados->cambios[0]->nuevo)->toBe('https://docs.agropartners.com.bo/A-0142-tds-v2.pdf');
});

it('vaciar un campo lo quita', function () {
    cambiarGliforte(['hojaDeSeguridad' => '']);

    expect(DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte)->value('hoja_seguridad_url'))->toBeNull();

    $tipos = array_column($this->getJson('/v1/productos/A-0142/documentos', ['Authorization' => 'Bearer token-bueno'])->json('data.items'), 'tipo');
    expect($tipos)->toBe(['ficha_tecnica']);
});

it('sin cambios no toca nada', function () {
    $tabla = DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte);
    $antes = $tabla->value('actualizado_en');

    $this->travel(5)->minutes();
    $cambiados = cambiadosDe(cambiarGliforte());

    expect($cambiados->cambios)->toBe([])
        ->and(DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte)->value('actualizado_en'))->toBe($antes);
});

it('la API devuelve la URL ya codificada', function () {
    cambiarGliforte(['imagen' => 'https://cdn.agropartners.com.bo/productos/Gliforte® Plus.jpg']);

    $this->getJson('/v1/productos/A-0142', ['Authorization' => 'Bearer token-bueno'])
        ->assertJsonPath('data.imagenUrl', 'https://cdn.agropartners.com.bo/productos/Gliforte%C2%AE%20Plus.jpg');
});

it('un enlace invalido no guarda ninguno', function () {
    // Todo o nada: si la ficha está mal, tampoco se guarda la imagen que sí
    // estaba bien, así el formulario vuelve tal como lo dejó la persona.
    $resultado = cambiarGliforte([
        'imagen' => 'https://cdn.agropartners.com.bo/productos/nueva.png',
        'fichaTecnica' => 'https://docs.agropartners.com.bo/ficha.jpg',
    ]);

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error)->toBeInstanceOf(ValidationError::class);

    assert($resultado->error instanceof ValidationError);
    $error = $resultado->error->errors()[0];

    expect($error)->toBeInstanceOf(FieldError::class)
        ->and($error instanceof FieldError ? $error->field : null)->toBe('ficha_tecnica_url')
        ->and(DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $this->gliforte)->value('imagen_url'))->toBe(IMAGEN_DE_GLIFORTE);
});

it('un producto sin codigo tambien se edita', function () {
    $sinCodigo = CatalogoDeEjemplo::idDe('Sin código SAP todavía');

    $cambiados = cambiadosDe(app(Mediator::class)->send(
        new CambiarEnlacesDeProducto($sinCodigo, 'https://cdn.x.bo/a.jpg', null, null, null),
    ));

    expect($cambiados->itemCode)->toBeNull()
        ->and($cambiados->cambios)->toHaveCount(1);
});

it('un producto que no existe', function () {
    $resultado = app(Mediator::class)->send(new CambiarEnlacesDeProducto(999999, null, null, null, null));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('PRODUCTO_NO_ENCONTRADO');
});
