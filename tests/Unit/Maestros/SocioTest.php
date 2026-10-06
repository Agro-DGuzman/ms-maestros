<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;

it('acepta un codigo de socio de hasta 15 caracteres', function () {
    expect(CodigoDeSocio::desde('C-004871')->value())->toBe('C-004871');
});

it('rechaza un codigo vacio o demasiado largo', function (string $malo) {
    expect(fn () => CodigoDeSocio::desde($malo))->toThrow(DomainException::class);
})->with(['', '   ', 'C-0000000000000000']);

it('deriva las iniciales de la razon social y no las guarda', function (string $razon, string $esperado) {
    expect((string) RazonSocial::desde($razon)->iniciales())->toBe($esperado);
})->with([
    ['Sebastian Monasterio', 'SM'],
    ['Fernando  Monasterio', 'FM'],
    ['Agropecuaria del Norte SRL', 'AD'],
    ['Monasterio', 'M'],
]);

it('rechaza una razon social vacia', function () {
    expect(fn () => RazonSocial::desde('  '))->toThrow(DomainException::class);
});

it('construye una replica y no expone constructor publico', function () {
    $socio = Socio::replica(
        CodigoDeSocio::desde('C-004871'),
        RazonSocial::desde('Sebastian Monasterio'),
        IdDeGrupo::desde('GRP-014'),
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    );

    expect($socio->codigoDeSocio()->value())->toBe('C-004871')
        ->and((string) $socio->razonSocial()->iniciales())->toBe('SM')
        ->and($socio->grupo()?->value())->toBe('GRP-014')
        ->and((new ReflectionClass(Socio::class))->getConstructor()->isPrivate())->toBeTrue();
});

it('una replica no retrocede', function () {
    $socio = Socio::replica(
        CodigoDeSocio::desde('C-004871'),
        RazonSocial::desde('Sebastian Monasterio'),
        IdDeGrupo::desde('GRP-014'),
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    );

    expect($socio->debeReemplazarA(new DateTimeImmutable('2026-08-19T11:00:00Z')))->toBeTrue()
        ->and($socio->debeReemplazarA(new DateTimeImmutable('2026-08-19T11:42:07Z')))->toBeFalse()
        ->and($socio->debeReemplazarA(new DateTimeImmutable('2026-08-20T00:00:00Z')))->toBeFalse();
});

function socioDePrueba(bool $activo = true, ?DateTimeImmutable $baja = null): Socio
{
    return Socio::replica(
        CodigoDeSocio::desde('C-900001'),
        RazonSocial::desde('Agro Prueba SRL'),
        IdDeGrupo::desde('GRP-900'),
        new DateTimeImmutable('2026-10-05T12:00:00Z'),
        activo: $activo,
        dadoDeBajaEl: $baja,
    );
}

it('un socio activo y sin baja es visible', function () {
    expect(socioDePrueba()->esVisible())->toBeTrue()
        ->and(socioDePrueba(activo: false)->esVisible())->toBeFalse()
        ->and(socioDePrueba(baja: new DateTimeImmutable('2026-10-05T13:00:00Z'))->esVisible())->toBeFalse();
});

it('darlo de baja fija la baja y la vigencia en el mismo momento', function () {
    // La vigencia de la baja es lo que impide que un envío leído en SAP antes
    // de la baja lo resucite.
    $momento = new DateTimeImmutable('2026-10-05T15:00:00Z');
    $dado = socioDePrueba()->dadoDeBaja($momento);

    expect($dado->dadoDeBajaEl())->toEqual($momento)
        ->and($dado->vigenteDesde())->toEqual($momento)
        ->and($dado->esVisible())->toBeFalse();
});

it('un socio puede no tener grupo economico', function () {
    $sinGrupo = Socio::replica(
        CodigoDeSocio::desde('C-900001'),
        RazonSocial::desde('Agro Prueba SRL'),
        null,
        new DateTimeImmutable('2026-10-05T12:00:00Z'),
    );

    expect($sinGrupo->grupo())->toBeNull();
});
