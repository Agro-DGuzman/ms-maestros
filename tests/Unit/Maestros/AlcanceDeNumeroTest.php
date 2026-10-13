<?php

declare(strict_types=1);

use Core\Results\ErrorType;
use Maestros\Domain\Contactos\AlcanceDeNumero;
use Maestros\Domain\Contactos\ContactoErrors;
use Maestros\Domain\Contactos\MotivoDeNoIngreso;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;

/** @return array{socio: CodigoDeSocio, grupo: ?IdDeGrupo} */
function v(string $socio, ?string $grupo): array
{
    return ['socio' => CodigoDeSocio::desde($socio), 'grupo' => $grupo === null ? null : IdDeGrupo::desde($grupo)];
}

/** @return list<string> */
function codigos(AlcanceDeNumero $alcance): array
{
    return array_map(static fn (CodigoDeSocio $s): string => $s->value(), $alcance->socios());
}

it('sin vinculos no entra', function () {
    $alcance = AlcanceDeNumero::desde([]);

    expect($alcance->entra())->toBeFalse()
        ->and($alcance->motivo())->toBe(MotivoDeNoIngreso::SinContactos)
        ->and($alcance->socios())->toBe([]);
});

it('solo con socios sin grupo no entra', function () {
    expect(AlcanceDeNumero::desde([v('C-1', null)])->motivo())->toBe(MotivoDeNoIngreso::SoloSinGrupo);
});

it('con socios de dos grupos no entra', function () {
    expect(AlcanceDeNumero::desde([v('C-1', 'G1'), v('C-2', 'G2')])->motivo())->toBe(MotivoDeNoIngreso::VariosGrupos);
});

it('un grupo con varios socios entra e ignora los sin grupo', function () {
    $alcance = AlcanceDeNumero::desde([v('C-1', 'G1'), v('C-3', null), v('C-2', 'G1'), v('C-1', 'G1')]);

    expect($alcance->entra())->toBeTrue()
        ->and($alcance->motivo())->toBeNull()
        ->and($alcance->grupo()?->value())->toBe('G1')
        ->and(codigos($alcance))->toBe(['C-1', 'C-2'])
        ->and($alcance->alcanza(CodigoDeSocio::desde('C-2')))->toBeTrue()
        ->and($alcance->alcanza(CodigoDeSocio::desde('C-3')))->toBeFalse();
});

it('un numero que no entra no alcanza nada', function () {
    $alcance = AlcanceDeNumero::desde([v('C-1', 'G1'), v('C-2', 'G2')]);

    expect($alcance->grupo())->toBeNull()
        ->and($alcance->socios())->toBe([])
        ->and($alcance->alcanza(CodigoDeSocio::desde('C-1')))->toBeFalse();
});

it('los codigos de error dicen por que no entra', function (MotivoDeNoIngreso $motivo, string $codigo) {
    $error = ContactoErrors::numeroNoIngresa($motivo);

    expect($error->code)->toBe($codigo)
        ->and($error->type)->toBe(ErrorType::Validation);
})->with(fn () => [
    [MotivoDeNoIngreso::SinContactos, 'NUMERO_SIN_CONTACTOS'],
    [MotivoDeNoIngreso::SoloSinGrupo, 'NUMERO_SOLO_SIN_GRUPO'],
    [MotivoDeNoIngreso::VariosGrupos, 'NUMERO_EN_VARIOS_GRUPOS'],
]);
