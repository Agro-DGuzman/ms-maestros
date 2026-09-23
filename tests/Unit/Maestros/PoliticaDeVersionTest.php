<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Aplicacion\AvisoDeVersion;
use Maestros\Domain\Aplicacion\EstadoDeVersion;
use Maestros\Domain\Aplicacion\PoliticaDeVersion;
use Maestros\Domain\Aplicacion\Version;

function politica(?string $minima, ?string $recomendada): PoliticaDeVersion
{
    return new PoliticaDeVersion(
        $minima === null ? null : Version::desde($minima),
        $recomendada === null ? null : Version::desde($recomendada),
        'https://play.google.com/store/apps/details?id=bo.com.agropartners.socio',
        new AvisoDeVersion('Actualización necesaria', 'Esta versión dejó de estar disponible.'),
        new AvisoDeVersion('Hay una versión nueva', 'Puedes actualizar cuando quieras.'),
    );
}

it('compara las versiones por numero, no por texto', function () {
    // Como texto, "1.10.0" es menor que "1.9.0", y una versión nueva quedaría
    // bloqueada por una mínima vieja.
    expect(Version::desde('1.9.0')->menorQue(Version::desde('1.10.0')))->toBeTrue()
        ->and(Version::desde('1.10.0')->menorQue(Version::desde('1.9.0')))->toBeFalse()
        ->and(Version::desde('2.0.0')->menorQue(Version::desde('2.0.0')))->toBeFalse();
});

it('rechaza una version sin formato semantico', function (string $texto) {
    expect(fn () => Version::desde($texto))->toThrow(DomainException::class);
})->with(['2.1', '2.1.0-beta', 'v2.1.0', '']);

it('por debajo de la minima exige actualizar y no deja continuar', function () {
    $estado = politica('1.8.0', '2.1.0')->evaluar(Version::desde('1.7.2'));

    expect($estado)->toBe(EstadoDeVersion::ActualizacionObligatoria)
        ->and($estado->permiteContinuar())->toBeFalse()
        ->and(politica('1.8.0', '2.1.0')->aviso($estado)?->titulo)->toBe('Actualización necesaria');
});

it('en la minima exacta todavia deja continuar', function () {
    expect(politica('1.8.0', '2.1.0')->evaluar(Version::desde('1.8.0'))->permiteContinuar())->toBeTrue();
});

it('entre la minima y la recomendada sugiere, y deja continuar', function () {
    $estado = politica('1.8.0', '2.1.0')->evaluar(Version::desde('2.0.0'));

    expect($estado)->toBe(EstadoDeVersion::ActualizacionSugerida)
        ->and($estado->permiteContinuar())->toBeTrue()
        ->and(politica('1.8.0', '2.1.0')->aviso($estado)?->titulo)->toBe('Hay una versión nueva');
});

it('al dia no avisa nada', function () {
    $estado = politica('1.8.0', '2.1.0')->evaluar(Version::desde('2.1.0'));

    expect($estado)->toBe(EstadoDeVersion::Vigente)
        ->and(politica('1.8.0', '2.1.0')->aviso($estado))->toBeNull();
});

it('sin versiones configuradas nunca bloquea', function () {
    // Una variable que falta no puede dejar a todos afuera: el control de
    // versión existe para las emergencias, no para fallar cerrado.
    expect(politica(null, null)->evaluar(Version::desde('0.0.1')))->toBe(EstadoDeVersion::Vigente);
});
