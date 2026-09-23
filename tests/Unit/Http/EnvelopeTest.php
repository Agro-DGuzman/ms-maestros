<?php

declare(strict_types=1);

use App\Http\Envelope;
use App\Http\MapaDeErroresHttp;
use Core\Results\Error;
use Core\Results\FieldError;
use Core\Results\Result;
use Core\Results\ValidationError;

it('el exito lleva data y error nulo, y nada mas', function () {
    $sobre = Envelope::exito(['cardCode' => 'C-004871']);

    expect(array_keys($sobre))->toBe(['data', 'success', 'error'])
        ->and($sobre['success'])->toBeTrue()
        ->and($sobre['error'])->toBeNull()
        ->and($sobre['data'])->toBe(['cardCode' => 'C-004871']);
});

it('el fallo lleva data nula, el code como lista y ningun desglose si no es de un campo', function () {
    $sobre = Envelope::fallo(Error::notFound('SOCIO_NO_ENCONTRADO', 'No existe el socio {codigo}', 'C-1'));

    expect($sobre['data'])->toBeNull()
        ->and($sobre['success'])->toBeFalse()
        ->and($sobre['error']['code'])->toBe(['SOCIO_NO_ENCONTRADO'])
        ->and($sobre['error']['structuredMessage'])->toBe([])
        ->and($sobre['error']['description'])->toBe('No existe el socio C-1')
        ->and($sobre['error']['type'])->toBe('NOT_FOUND');
});

it('desglosa un error de validacion campo por campo, como lo pide el contrato', function () {
    $sobre = Envelope::fallo(ValidationError::fromResults(
        Result::failure(new FieldError('telefono', 'PARAMETRO_INVALIDO', 'No es un celular boliviano.')),
        Result::failure(new FieldError('codigo', 'PARAMETRO_INVALIDO', 'Valor inválido.')),
        Result::failure(Error::validation('SIN_CAMPO', 'Un error que no es de ningun campo')),
    ));

    // El code no repite: es la lista de clases de error, no una por campo.
    expect($sobre['error']['code'])->toBe(['PARAMETRO_INVALIDO', 'SIN_CAMPO'])
        ->and($sobre['error']['structuredMessage'])->toBe([
            ['campo' => 'telefono', 'codigo' => 'PARAMETRO_INVALIDO', 'mensaje' => 'No es un celular boliviano.'],
            ['campo' => 'codigo', 'codigo' => 'PARAMETRO_INVALIDO', 'mensaje' => 'Valor inválido.'],
        ])
        ->and($sobre['error']['type'])->toBe('VALIDATION');
});

it('traduce cada tipo a su codigo http', function () {
    expect(MapaDeErroresHttp::status(Error::validation('X', 'x')))->toBe(400)
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
    expect(MapaDeErroresHttp::status(Error::failure('TOKEN_INVALIDO', 'x')))->toBe(401)
        ->and(MapaDeErroresHttp::status(Error::failure('CODIGO_INVALIDO', 'x')))->toBe(401)
        ->and(MapaDeErroresHttp::status(Error::failure('REFRESH_TOKEN_INVALIDO', 'x')))->toBe(401)
        ->and(MapaDeErroresHttp::status(Error::failure('ACCESO_DENEGADO', 'x')))->toBe(403)
        ->and(MapaDeErroresHttp::status(Error::failure('LIMITE_TASA_SUPERADO', 'x')))->toBe(429);
});
