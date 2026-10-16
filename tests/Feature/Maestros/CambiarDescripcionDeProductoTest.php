<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\FieldError;
use Core\Results\ResultWithValue;
use Core\Results\ValidationError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Productos\CambiarDescripcion\CambiarDescripcionDeProducto;
use Maestros\Application\Productos\DescripcionCambiada;
use Tests\Soporte\CatalogoDeEjemplo;

uses(RefreshDatabase::class);

const DESCRIPCION_DE_LA_SEMILLA = 'Herbicida sistémico no selectivo de amplio espectro.';

beforeEach(function () {
    CatalogoDeEjemplo::sembrar();
    $this->gliforte = CatalogoDeEjemplo::idDe('Gliforte 68 SG');
});

function cambiarDescripcion(int $id, ?string $descripcion): ResultWithValue
{
    $resultado = app(Mediator::class)->send(new CambiarDescripcionDeProducto($id, $descripcion));
    assert($resultado instanceof ResultWithValue);

    return $resultado;
}

function descripcionGuardada(int $id): ?string
{
    $valor = DB::table(CatalogoDeEjemplo::tabla('producto'))->where('id_producto', $id)->value('descripcion');

    return is_string($valor) ? $valor : null;
}

it('cambia la descripcion y devuelve la anterior', function () {
    $resultado = cambiarDescripcion($this->gliforte, 'Nueva');

    $cambiada = $resultado->value();
    assert($cambiada instanceof DescripcionCambiada);

    expect($cambiada->anterior)->toBe(DESCRIPCION_DE_LA_SEMILLA)
        ->and($cambiada->nuevo)->toBe('Nueva')
        ->and($cambiada->cambio())->toBeTrue()
        ->and(descripcionGuardada($this->gliforte))->toBe('Nueva');
});

it('vacia o con espacios la quita', function () {
    $resultado = cambiarDescripcion($this->gliforte, '   ');

    $cambiada = $resultado->value();
    assert($cambiada instanceof DescripcionCambiada);

    expect($cambiada->nuevo)->toBeNull()
        ->and(descripcionGuardada($this->gliforte))->toBeNull();
});

it('la misma descripcion no es un cambio', function () {
    $resultado = cambiarDescripcion($this->gliforte, DESCRIPCION_DE_LA_SEMILLA);

    $cambiada = $resultado->value();
    assert($cambiada instanceof DescripcionCambiada);

    expect($cambiada->cambio())->toBeFalse();
});

/** 1000 es el largo de `valor_anterior` y `valor_nuevo` en la bitácora. */
it('mas de 1000 caracteres es un error del campo descripcion', function () {
    $resultado = cambiarDescripcion($this->gliforte, str_repeat('a', 1001));
    $error = $resultado->error;
    $campo = $error instanceof ValidationError ? ($error->errors()[0] ?? null) : null;

    expect($resultado->isFailure())->toBeTrue()
        ->and($campo)->toBeInstanceOf(FieldError::class)
        ->and($campo instanceof FieldError ? $campo->field : null)->toBe('descripcion')
        ->and(descripcionGuardada($this->gliforte))->toBe(DESCRIPCION_DE_LA_SEMILLA);
});

it('un id que no existe responde PRODUCTO_NO_ENCONTRADO', function () {
    $resultado = cambiarDescripcion(999999, 'Nueva');

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('PRODUCTO_NO_ENCONTRADO');
});
