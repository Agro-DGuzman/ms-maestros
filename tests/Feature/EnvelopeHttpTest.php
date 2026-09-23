<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Core\Results\Error;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_prueba/validacion', function (Request $r) {
        $r->validate(['celular' => 'required|string']);

        return response()->json(['ok' => true]);
    });

    Route::get('/_prueba/dominio', function () {
        throw new DomainException(Error::notFound('SOCIO_NO_ENCONTRADO', 'No existe el socio {codigo}', 'C-1'));
    });
});

it('una excepcion de dominio se renderiza en el envelope con su status', function () {
    $this->getJson('/_prueba/dominio')
        ->assertStatus(404)
        ->assertExactJson([
            'data' => null,
            'success' => false,
            'error' => [
                'code' => ['SOCIO_NO_ENCONTRADO'],
                'description' => 'No existe el socio C-1',
                'structuredMessage' => [],
                'type' => 'NOT_FOUND',
            ],
        ]);
});

it('la validacion de Laravel usa el mismo envelope, con 400 y el desglose por campo', function () {
    $respuesta = $this->postJson('/_prueba/validacion', [])->assertStatus(400);

    expect($respuesta->json('success'))->toBeFalse()
        ->and($respuesta->json('data'))->toBeNull()
        ->and($respuesta->json('error.type'))->toBe('VALIDATION')
        ->and($respuesta->json('error.code'))->toBe(['CAMPO_REQUERIDO'])
        ->and($respuesta->json('error.structuredMessage'))->toBe([
            ['campo' => 'celular', 'codigo' => 'CAMPO_REQUERIDO', 'mensaje' => 'Requerido.'],
        ]);
});
