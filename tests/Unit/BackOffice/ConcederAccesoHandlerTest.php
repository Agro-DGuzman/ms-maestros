<?php

declare(strict_types=1);

use BackOffice\Application\Accesos\ConcederAcceso\ConcederAcceso;
use BackOffice\Application\Accesos\ConcederAcceso\ConcederAccesoHandler;
use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Tests\Dobles\BitacoraEnMemoria;
use Tests\Dobles\MediatorEspia;
use Tests\Soporte\RelojFijo;

function comandoDeConceder(): ConcederAcceso
{
    return new ConcederAcceso(
        idDePersona: 'p-8f2b1c40',
        operador: new Operador(
            IdDeOperador::desdeOid('oid-77'),
            'Mónica Salvatierra',
            'monica@agropartners.com.bo',
        ),
        direccionIp: '190.129.4.7',
    );
}

function concederCon(MediatorEspia $mediator, BitacoraEnMemoria $bitacora): Result
{
    return (new ConcederAccesoHandler($mediator, $bitacora, new RelojFijo))
        ->handle(comandoDeConceder());
}

it('despacha HabilitarPersona con el id recibido', function () {
    $mediator = new MediatorEspia(Result::success());

    concederCon($mediator, new BitacoraEnMemoria);

    $despachado = $mediator->ultimo();

    expect($despachado)->toBeInstanceOf(HabilitarPersona::class)
        ->and($despachado->persona->value())->toBe('p-8f2b1c40');
});

it('asienta en la bitacora cuando la habilitacion tuvo exito', function () {
    $bitacora = new BitacoraEnMemoria;

    concederCon(new MediatorEspia(Result::success()), $bitacora);

    expect($bitacora->asientos)->toHaveCount(1)
        ->and($bitacora->asientos[0]->accion)->toBe(AccionDeAcceso::Concedio)
        ->and($bitacora->asientos[0]->idDePersona)->toBe('p-8f2b1c40')
        ->and($bitacora->asientos[0]->operador)->toBe('Mónica Salvatierra')
        ->and($bitacora->asientos[0]->idDeOperador->valor)->toBe('oid-77')
        ->and($bitacora->asientos[0]->direccionIp)->toBe('190.129.4.7');
});

it('no asienta nada cuando la habilitacion fallo', function () {
    $bitacora = new BitacoraEnMemoria;
    $fallo = Result::failure(Error::validation('CELULAR_INVALIDO', 'Corregilo en SAP.'));

    $resultado = concederCon(new MediatorEspia($fallo), $bitacora);

    // Una bitacora que registre intentos fallidos miente sobre quien tiene
    // acceso, que es exactamente la pregunta que se le va a hacer.
    expect($bitacora->asientos)->toBe([])
        ->and($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CELULAR_INVALIDO');
});

it('el asiento lleva el momento del reloj y no el del sistema', function () {
    $bitacora = new BitacoraEnMemoria;

    concederCon(new MediatorEspia(Result::success()), $bitacora);

    expect($bitacora->asientos[0]->ocurrioEl->format('c'))->toBe('2026-09-15T14:30:00+00:00');
});
