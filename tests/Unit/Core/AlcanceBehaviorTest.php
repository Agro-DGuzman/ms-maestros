<?php

declare(strict_types=1);

use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\Request;
use Core\Mediator\Behaviors\AlcanceBehavior;
use Core\Results\Result;
use Maestros\Application\Alcance\ResolutorDeAlcance;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;

final class PeticionConAlcance implements ConAlcanceDeSocio, Request
{
    public function __construct(
        private readonly IdDePersona $persona,
        private readonly ?CodigoDeSocio $cardCode,
    ) {}

    public function persona(): IdDePersona
    {
        return $this->persona;
    }

    public function cardCode(): ?CodigoDeSocio
    {
        return $this->cardCode;
    }
}

final class PeticionSinAlcance implements Request {}

final class ResolutorDePrueba implements ResolutorDeAlcance
{
    /** @param list<string> $permitidos */
    public function __construct(private readonly array $permitidos) {}

    public function alcanza(IdDePersona $persona, CodigoDeSocio $socio): bool
    {
        return in_array($socio->value(), $this->permitidos, true);
    }
}

function correr(Request $peticion, ResolutorDeAlcance $resolutor, mixed &$llegoAlHandler): Result
{
    $llegoAlHandler = false;

    return (new AlcanceBehavior($resolutor))->handle(
        $peticion,
        function () use (&$llegoAlHandler): Result {
            $llegoAlHandler = true;

            return Result::success();
        },
    );
}

it('deja pasar una peticion que no declara alcance', function () {
    $resultado = correr(new PeticionSinAlcance, new ResolutorDePrueba([]), $llego);

    expect($resultado->isSuccess)->toBeTrue()->and($llego)->toBeTrue();
});

it('deja pasar cuando el cardCode esta dentro del alcance', function () {
    $peticion = new PeticionConAlcance(IdDePersona::desde('p-1'), CodigoDeSocio::desde('C-004871'));

    $resultado = correr($peticion, new ResolutorDePrueba(['C-004871']), $llego);

    expect($resultado->isSuccess)->toBeTrue()->and($llego)->toBeTrue();
});

it('corta con ACCESO_DENEGADO antes del handler cuando esta fuera de alcance', function () {
    $peticion = new PeticionConAlcance(IdDePersona::desde('p-1'), CodigoDeSocio::desde('C-999'));

    $resultado = correr($peticion, new ResolutorDePrueba(['C-004871']), $llego);

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('ACCESO_DENEGADO')
        ->and($llego)->toBeFalse();
});

it('omitir el cardCode equivale a consultar todo el alcance y no corta', function () {
    $peticion = new PeticionConAlcance(IdDePersona::desde('p-1'), null);

    $resultado = correr($peticion, new ResolutorDePrueba([]), $llego);

    expect($resultado->isSuccess)->toBeTrue()->and($llego)->toBeTrue();
});

it('un codigo inexistente fuera de alcance da 403 y no 404', function () {
    // El alcance se verifica ANTES que la existencia: si no, la diferencia
    // entre 403 y 404 convierte al endpoint en un oráculo para enumerar los
    // códigos de socio que hay en SAP.
    $peticion = new PeticionConAlcance(IdDePersona::desde('p-1'), CodigoDeSocio::desde('C-INVENTADO'));

    $resultado = correr($peticion, new ResolutorDePrueba(['C-004871']), $llego);

    expect($resultado->error->code)->toBe('ACCESO_DENEGADO')->and($llego)->toBeFalse();
});
