<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

uses(RefreshDatabase::class);

function unSocio(string $codigo, string $razon, string $grupo = 'GRP-014'): Socio
{
    return Socio::replica(
        CodigoDeSocio::desde($codigo),
        RazonSocial::desde($razon),
        IdDeGrupo::desde($grupo),
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    );
}

it('guarda un socio y lo vuelve a cargar igual', function () {
    $repo = app(SocioRepository::class);
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio'));

    $recuperado = $repo->find(CodigoDeSocio::desde('C-004871'));

    expect($recuperado)->toBeInstanceOf(Socio::class)
        ->and($recuperado->razonSocial()->texto())->toBe('Sebastian Monasterio')
        ->and($recuperado->idDeGrupo()->value())->toBe('GRP-014');
});

it('save reemplaza en vez de duplicar', function () {
    $repo = app(SocioRepository::class);
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio'));
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio SRL'));

    expect($repo->find(CodigoDeSocio::desde('C-004871'))->razonSocial()->texto())
        ->toBe('Sebastian Monasterio SRL')
        ->and($repo->porGrupo(IdDeGrupo::desde('GRP-014')))->toHaveCount(1);
});

it('devuelve null cuando el socio no existe', function () {
    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-999')))->toBeNull();
});

it('lista los socios de un grupo y solo esos', function () {
    $repo = app(SocioRepository::class);
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio'));
    $repo->save(unSocio('C-004872', 'Fernando Monasterio'));
    $repo->save(unSocio('C-005000', 'Otro Productor', 'GRP-020'));

    $codigos = array_map(
        fn (Socio $s): string => $s->codigoDeSocio()->value(),
        $repo->porGrupo(IdDeGrupo::desde('GRP-014')),
    );

    expect($codigos)->toBe(['C-004871', 'C-004872']);
});

it('encuentra una persona de contacto por su celular normalizado', function () {
    app(SocioRepository::class)->save(unSocio('C-004871', 'Sebastian Monasterio'));

    $repo = app(ContactoRepository::class);
    $repo->save(PersonaDeContacto::replica(
        IdDePersona::desde('p-8f2b1c40'),
        CodigoDeSocio::desde('C-004871'),
        'Monica Salvatierra',
        Celular::desdeLocalBoliviano('707 41 828'),
        new DateTimeImmutable('2024-02-14T00:00:00Z'),
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    ));

    $persona = $repo->porCelular(Celular::desdeLocalBoliviano('70741828'));

    expect($persona)->not->toBeNull()
        ->and($persona->estaHabilitada())->toBeTrue()
        ->and($persona->codigoDeSocio()->value())->toBe('C-004871');
});
