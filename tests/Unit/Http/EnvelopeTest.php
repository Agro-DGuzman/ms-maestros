<?php

declare(strict_types=1);

use App\Http\Envelope;
use App\Http\MapaDeErroresHttp;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ValidationError;

it('el exito lleva data y error nulo, y nada mas', function () {
    $sobre = Envelope::exito(['cardCode' => 'C-004871']);

    expect(array_keys($sobre))->toBe(['data', 'success', 'error'])
        ->and($sobre['success'])->toBeTrue()
        ->and($sobre['error'])->toBeNull()
        ->and($sobre['data'])->toBe(['cardCode' => 'C-004871']);
});

it('el fallo lleva data nula y el error con code y structuredMessage como listas', function () {
    $sobre = Envelope::fallo(Error::notFound('SOCIO_NO_ENCONTRADO', 'No existe el socio {codigo}', 'C-1'));

    expect($sobre['data'])->toBeNull()
        ->and($sobre['success'])->toBeFalse()
        ->and($sobre['error']['code'])->toBe(['SOCIO_NO_ENCONTRADO'])
        ->and($sobre['error']['structuredMessage'])->toBe(['No existe el socio {codigo}'])
        ->and($sobre['error']['description'])->toBe('No existe el socio C-1')
        ->and($sobre['error']['type'])->toBe('NOT_FOUND');
});

it('aplana un error de validacion en varias entradas', function () {
    $sobre = Envelope::fallo(ValidationError::fromResults(
        Result::failure(Error::validation('CELULAR_INVALIDO', 'El celular {n} no es valido', '123')),
        Result::failure(Error::validation('CODIGO_INVALIDO', 'El codigo no es valido')),
    ));

    expect($sobre['error']['code'])->toBe(['CELULAR_INVALIDO', 'CODIGO_INVALIDO'])
        ->and($sobre['error']['structuredMessage'])->toHaveCount(2)
        ->and($sobre['error']['type'])->toBe('VALIDATION');
});

it('traduce cada tipo a su codigo http', function () {
    expect(MapaDeErroresHttp::status(Error::validation('X', 'x')))->toBe(422)
        ->and(MapaDeErroresHttp::status(Error::notFound('X', 'x')))->toBe(404)
        ->and(MapaDeErroresHttp::status(Error::conflict('X', 'x')))->toBe(409)
        ->and(MapaDeErroresHttp::status(Error::problem('X', 'x')))->toBe(500)
        ->and(MapaDeErroresHttp::status(Error::failure('X', 'x')))->toBe(500);
});

it('un FAILURE de codigo desconocido no se confunde con acceso denegado', function () {
    expect(MapaDeErroresHttp::status(Error::failure('ALGO_RARO', 'x')))->toBe(500)
        ->and(MapaDeErroresHttp::status(Error::failure('ACCESO_DENEGADO', 'x')))->toBe(403);
});

it('los codigos con status propio ganan sobre el tipo', function () {
    expect(MapaDeErroresHttp::status(Error::failure('NO_AUTENTICADO', 'x')))->toBe(401)
        ->and(MapaDeErroresHttp::status(Error::failure('ACCESO_DENEGADO', 'x')))->toBe(403)
        ->and(MapaDeErroresHttp::status(Error::failure('LIMITE_DE_TASA', 'x')))->toBe(429);
});
