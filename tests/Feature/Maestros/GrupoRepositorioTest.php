<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;

uses(RefreshDatabase::class);

it('guarda un grupo y lo vuelve a cargar', function () {
    $repo = app(GrupoRepository::class);

    $repo->save(GrupoEconomico::replica(
        IdDeGrupo::desde('GRP-014'),
        'Grupo Monasterio',
        new DateTimeImmutable('2026-09-11T12:00:00Z'),
    ));

    $grupo = $repo->find(IdDeGrupo::desde('GRP-014'));

    expect($grupo)->toBeInstanceOf(GrupoEconomico::class)
        ->and($grupo->nombre())->toBe('Grupo Monasterio');
});

it('save reemplaza en vez de duplicar', function () {
    $repo = app(GrupoRepository::class);
    $marca = new DateTimeImmutable('2026-09-11T12:00:00Z');

    $repo->save(GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Viejo', $marca));
    $repo->save(GrupoEconomico::replica(IdDeGrupo::desde('GRP-014'), 'Nuevo', $marca));

    expect($repo->find(IdDeGrupo::desde('GRP-014'))->nombre())->toBe('Nuevo');
});

it('devuelve null cuando el grupo no existe', function () {
    expect(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-999')))->toBeNull();
});
