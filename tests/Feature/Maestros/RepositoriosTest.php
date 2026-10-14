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
use Maestros\Infrastructure\Persistence\SocioRecord;

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
        ->and($recuperado->grupo()?->value())->toBe('GRP-014');
});

it('save reemplaza en vez de duplicar', function () {
    $repo = app(SocioRepository::class);
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio'));
    $repo->save(unSocio('C-004871', 'Sebastian Monasterio SRL'));

    expect($repo->find(CodigoDeSocio::desde('C-004871'))->razonSocial()->texto())
        ->toBe('Sebastian Monasterio SRL')
        ->and(SocioRecord::query()->where('codigo_de_socio', 'C-004871')->count())->toBe(1);
});

it('devuelve null cuando el socio no existe', function () {
    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-999')))->toBeNull();
});

it('guarda el celular normalizado de la persona de contacto', function () {
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

    $persona = $repo->find(IdDePersona::desde('p-8f2b1c40'));

    expect($persona?->celular()?->e164())->toBe('+59170741828')
        ->and($persona->estaHabilitada())->toBeTrue()
        ->and($persona->codigoDeSocio()->value())->toBe('C-004871');
});
