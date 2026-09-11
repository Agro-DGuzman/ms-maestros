<?php

declare(strict_types=1);

use Core\Results\Error;
use Core\Results\ErrorType;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Core\Results\ValidationError;

it('serializa cada tipo con el valor que espera el contrato', function () {
    expect(ErrorType::Validation->value)->toBe('VALIDATION')
        ->and(ErrorType::NotFound->value)->toBe('NOT_FOUND')
        ->and(ErrorType::Conflict->value)->toBe('CONFLICT')
        ->and(ErrorType::Failure->value)->toBe('FAILURE')
        ->and(ErrorType::Problem->value)->toBe('PROBLEM');
});

it('reemplaza los marcadores del mensaje estructurado en orden', function () {
    $error = Error::notFound('SOCIO_NO_ENCONTRADO', 'No existe el socio {codigo} en {origen}', 'C-004871', 'SAP');

    expect($error->description)->toBe('No existe el socio C-004871 en SAP')
        ->and($error->structuredMessage)->toBe('No existe el socio {codigo} en {origen}');
});

it('deja el marcador intacto cuando faltan argumentos', function () {
    $error = Error::failure('X', 'Falta {a} y {b}', 'uno');

    expect($error->description)->toBe('Falta uno y {b}');
});

it('no deja construir un exito con error ni un fallo sin error', function () {
    expect(fn () => Result::failure(Error::none()))
        ->toThrow(InvalidArgumentException::class);
});

it('un exito no lleva error y un fallo no lleva valor', function () {
    $ok = Result::success();
    $mal = Result::failure(Error::conflict('DUPLICADO', 'Ya existe'));

    expect($ok->isSuccess)->toBeTrue()
        ->and($ok->error)->toBe(Error::none())
        ->and($mal->isFailure())->toBeTrue()
        ->and($mal->error->code)->toBe('DUPLICADO');
});

it('of devuelve fallo cuando el valor es nulo', function () {
    expect(ResultWithValue::of(null)->isFailure())->toBeTrue()
        ->and(ResultWithValue::of('algo')->value())->toBe('algo');
});

it('no deja leer el valor de un resultado fallido', function () {
    $r = ResultWithValue::failure(Error::notFound('NO_HAY', 'No hay'));

    expect(fn () => $r->value())->toThrow(LogicException::class);
});

it('agrupa varios errores de validacion', function () {
    $v = ValidationError::fromResults(
        Result::failure(Error::validation('NIT_INVALIDO', 'El NIT no es valido')),
        Result::failure(Error::validation('CELULAR_INVALIDO', 'El celular no es valido')),
        Result::success(),
    );

    expect($v->type)->toBe(ErrorType::Validation)
        ->and(array_map(fn (Error $e) => $e->code, $v->errors()))
        ->toBe(['NIT_INVALIDO', 'CELULAR_INVALIDO']);
});
