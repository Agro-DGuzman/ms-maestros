<?php

declare(strict_types=1);

use BackOffice\Application\Catalogo\ActualizarEnlaces\ActualizarEnlaces;
use BackOffice\Application\Catalogo\ActualizarEnlaces\ActualizarEnlacesHandler;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProducto;
use Maestros\Application\Productos\CambioDeEnlace;
use Maestros\Application\Productos\CampoDeEnlace;
use Maestros\Application\Productos\EnlacesCambiados;
use Tests\Dobles\MediatorEspia;
use Tests\Dobles\RegistroDeCambiosEnMemoria;
use Tests\Soporte\RelojFijo;

function actualizarCon(MediatorEspia $mediator, RegistroDeCambiosEnMemoria $registro): Result
{
    return (new ActualizarEnlacesHandler($mediator, $registro, new RelojFijo))->handle(new ActualizarEnlaces(
        idProducto: 7,
        imagen: 'https://cdn.x.bo/a.jpg',
        fichaTecnica: 'https://cdn.x.bo/f.pdf',
        hojaDeSeguridad: null,
        registroSanitario: '',
        operador: new Operador(IdDeOperador::desdeOid('oid-77'), 'Mónica Salvatierra', 'monica@agropartners.com.bo', Permiso::todos()),
        direccionIp: '190.129.4.7',
    ));
}

function cambiaron(CambioDeEnlace ...$cambios): MediatorEspia
{
    return new MediatorEspia(ResultWithValue::of(new EnlacesCambiados(7, 'A-0142', array_values($cambios))));
}

it('despacha CambiarEnlacesDeProducto con lo recibido', function () {
    $mediator = cambiaron();
    actualizarCon($mediator, new RegistroDeCambiosEnMemoria);

    $pedido = $mediator->ultimo();

    expect($pedido)->toBeInstanceOf(CambiarEnlacesDeProducto::class);
    assert($pedido instanceof CambiarEnlacesDeProducto);
    expect([$pedido->idProducto, $pedido->imagen, $pedido->fichaTecnica, $pedido->hojaDeSeguridad, $pedido->registroSanitario])
        ->toBe([7, 'https://cdn.x.bo/a.jpg', 'https://cdn.x.bo/f.pdf', null, '']);
});

it('asienta un cambio por campo, con el operador y la IP', function () {
    $registro = new RegistroDeCambiosEnMemoria;

    $resultado = actualizarCon(cambiaron(
        new CambioDeEnlace(CampoDeEnlace::Imagen, null, 'https://cdn.x.bo/a.jpg'),
        new CambioDeEnlace(CampoDeEnlace::FichaTecnica, 'https://cdn.x.bo/vieja.pdf', 'https://cdn.x.bo/f.pdf'),
    ), $registro);

    assert($resultado instanceof ResultWithValue);

    expect($resultado->value())->toBe(2)
        ->and($registro->cambios)->toHaveCount(2)
        ->and($registro->cambios[0]->operador)->toBe('Mónica Salvatierra')
        ->and($registro->cambios[0]->direccionIp)->toBe('190.129.4.7')
        ->and($registro->cambios[0]->itemCode)->toBe('A-0142')
        ->and($registro->cambios[0]->campo)->toBe('imagen_url')
        ->and($registro->cambios[1]->anterior)->toBe('https://cdn.x.bo/vieja.pdf')
        ->and($registro->cambios[1]->ocurrioEl->format(DATE_ATOM))->toBe('2026-09-15T14:30:00+00:00');
});

it('sin cambios no asienta nada', function () {
    $registro = new RegistroDeCambiosEnMemoria;

    $resultado = actualizarCon(cambiaron(), $registro);
    assert($resultado instanceof ResultWithValue);

    expect($resultado->value())->toBe(0)
        ->and($registro->cambios)->toBe([]);
});

it('si Maestros falla no asienta nada', function () {
    // Una bitácora que registre intentos fallidos miente sobre qué enlace
    // está publicado, que es justo lo que se le va a preguntar.
    $registro = new RegistroDeCambiosEnMemoria;
    $fallo = ResultWithValue::failure(Error::notFound('PRODUCTO_NO_ENCONTRADO', 'No existe'));

    $resultado = actualizarCon(new MediatorEspia($fallo), $registro);

    expect($resultado)->toBe($fallo)
        ->and($registro->cambios)->toBe([]);
});
