<?php

declare(strict_types=1);

use Maestros\Application\Eventos\SocioActualizado;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\LineaDeCredito;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Tests\Soporte\Contrato;

function socioParaElEvento(
    string $vigente = '2026-10-29T14:00:00Z',
    ?string $grupo = '1435',
    ?string $linea = '50000.00',
    bool $activo = true,
    ?string $baja = null,
): Socio {
    return Socio::replica(
        CodigoDeSocio::desde('10057'),
        RazonSocial::desde('AGROPECUARIA SOGIMA SRL'),
        $grupo === null ? null : IdDeGrupo::desde($grupo),
        new DateTimeImmutable($vigente),
        activo: $activo,
        dadoDeBajaEl: $baja === null ? null : new DateTimeImmutable($baja),
        lineaDeCredito: $linea === null ? null : LineaDeCredito::desde($linea),
    );
}

function grupoSogima(): GrupoEconomico
{
    return GrupoEconomico::replica(IdDeGrupo::desde('1435'), 'SOGIMA', new DateTimeImmutable('2026-10-13T16:50:19Z'));
}

it('arma el sobre de la spec y valida contra el contrato', function () {
    $evento = SocioActualizado::de(socioParaElEvento(), grupoSogima());

    expect($evento)->not->toBeNull();
    $sobre = $evento->sobre;

    expect($evento->tipo)->toBe('maestros.socio.actualizado')
        ->and($sobre['tipo'])->toBe('maestros.socio.actualizado')
        ->and($sobre['origen'])->toBe('ms-maestros')
        ->and($sobre['version'])->toBe(1)
        ->and($sobre['clave'])->toBe(['objeto' => 'socio', 'cardCode' => '10057'])
        ->and($sobre['ocurridoEn'])->toBe('2026-10-29T14:00:00.000000Z')
        ->and($sobre['datos'])->toBe([
            'cardCode' => '10057',
            'razonSocial' => 'AGROPECUARIA SOGIMA SRL',
            'grupoEconomico' => ['codigo' => '1435', 'nombre' => 'SOGIMA', 'segmento' => null],
            'lineaDeCredito' => '50000.00',
            'deBaja' => false,
        ])
        ->and(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'SocioActualizado'))->toBe([])
        // Que el validador mire de verdad: la línea como número no cumple.
        ->and(Contrato::diferenciasDeEsquema(array_replace_recursive($sobre, ['datos' => ['lineaDeCredito' => 50000]]), 'eventos-de-dominio-v1.yaml', 'SocioActualizado'))->not->toBe([]);
});

it('un ocurridoEn con desfase sale en UTC con Z', function () {
    $evento = SocioActualizado::de(socioParaElEvento(vigente: '2026-10-29T10:00:00-04:00'), grupoSogima());

    expect($evento?->sobre['ocurridoEn'])->toBe('2026-10-29T14:00:00.000000Z');
});

it('sin grupo, o con un grupo que no esta guardado, sale con grupoEconomico null y valida', function (?string $grupo) {
    $evento = SocioActualizado::de(socioParaElEvento(grupo: $grupo), null);

    expect($evento?->sobre['datos']['grupoEconomico'])->toBeNull()
        ->and(Contrato::diferenciasDeEsquema($evento?->sobre, 'eventos-de-dominio-v1.yaml', 'SocioActualizado'))->toBe([]);
})->with(['sin grupo' => [null], 'grupo que no esta guardado' => ['GRP-099']]);

it('un socio dado de baja sale con deBaja true, y uno inactivo con deBaja false', function () {
    $dado = SocioActualizado::de(socioParaElEvento(baja: '2026-10-29T15:00:00Z'), grupoSogima());
    $inactivo = SocioActualizado::de(socioParaElEvento(activo: false), grupoSogima());

    expect($dado?->sobre['datos']['deBaja'])->toBeTrue()
        ->and($inactivo?->sobre['datos']['deBaja'])->toBeFalse();
});

it('cada foto lleva un eventoId distinto, UUID v4 en minusculas', function () {
    $uno = SocioActualizado::de(socioParaElEvento(), grupoSogima());
    $otro = SocioActualizado::de(socioParaElEvento(), grupoSogima());

    expect($uno?->eventoId)->not->toBe($otro?->eventoId)
        ->and($uno?->eventoId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($otro?->eventoId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($uno?->sobre['eventoId'])->toBe($uno?->eventoId);
});

it('un socio sin linea no se publica', function () {
    expect(SocioActualizado::de(socioParaElEvento(linea: null), null))->toBeNull();
});
