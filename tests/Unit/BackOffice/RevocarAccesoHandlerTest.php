<?php

declare(strict_types=1);

use BackOffice\Application\Accesos\RevocarAcceso\RevocarAcceso;
use BackOffice\Application\Accesos\RevocarAcceso\RevocarAccesoHandler;
use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Tests\Dobles\BitacoraEnMemoria;
use Tests\Dobles\MediatorEspia;
use Tests\Soporte\RelojFijo;

function comandoDeRevocar(): RevocarAcceso
{
    return new RevocarAcceso(
        idDePersona: 'p-8f2b1c40',
        operador: new Operador(
            IdDeOperador::desdeOid('oid-77'),
            'Mónica Salvatierra',
            'monica@agropartners.com.bo',
        ),
        direccionIp: '190.129.4.7',
    );
}

function revocarCon(MediatorEspia $mediator, BitacoraEnMemoria $bitacora): Result
{
    return (new RevocarAccesoHandler($mediator, $bitacora, new RelojFijo))
        ->handle(comandoDeRevocar());
}

it('despacha DeshabilitarPersona con el id recibido', function () {
    $mediator = new MediatorEspia(Result::success());

    revocarCon($mediator, new BitacoraEnMemoria);

    $despachado = $mediator->ultimo();

    expect($despachado)->toBeInstanceOf(DeshabilitarPersona::class)
        ->and($despachado->persona->value())->toBe('p-8f2b1c40');
});

it('asienta la revocacion cuando la deshabilitacion tuvo exito', function () {
    $bitacora = new BitacoraEnMemoria;

    revocarCon(new MediatorEspia(Result::success()), $bitacora);

    expect($bitacora->asientos)->toHaveCount(1)
        ->and($bitacora->asientos[0]->accion)->toBe(AccionDeAcceso::Revoco)
        ->and($bitacora->asientos[0]->idDePersona)->toBe('p-8f2b1c40')
        ->and($bitacora->asientos[0]->operador)->toBe('Mónica Salvatierra');
});

it('no asienta nada cuando la deshabilitacion fallo', function () {
    $bitacora = new BitacoraEnMemoria;
    $fallo = Result::failure(Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'));

    $resultado = revocarCon(new MediatorEspia($fallo), $bitacora);

    expect($bitacora->asientos)->toBe([])
        ->and($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE');
});
