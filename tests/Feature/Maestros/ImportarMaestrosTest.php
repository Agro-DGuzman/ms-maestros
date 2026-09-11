<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\SocioRepository;

uses(RefreshDatabase::class);

it('importa el archivo de ejemplo', function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')])
        ->assertExitCode(0);

    expect(app(SocioRepository::class)->porGrupo(IdDeGrupo::desde('GRP-014')))->toHaveCount(3)
        ->and(app(ContactoRepository::class)->porCelular(Celular::desdeLocalBoliviano('70741828')))
        ->not->toBeNull();
});

it('se puede volver a correr sin duplicar', function () {
    $archivo = database_path('semillas/maestros-ejemplo.json');

    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);
    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);

    expect(app(SocioRepository::class)->porGrupo(IdDeGrupo::desde('GRP-014')))->toHaveCount(3);
});

it('falla con codigo 1 si el archivo no existe', function () {
    $this->artisan('maestros:importar', ['archivo' => '/no/existe.json'])->assertExitCode(1);
});

it('falla con codigo 1 si el JSON esta roto', function () {
    $roto = tempnam(sys_get_temp_dir(), 'roto').'.json';
    file_put_contents($roto, '{ esto no es json');

    $this->artisan('maestros:importar', ['archivo' => $roto])->assertExitCode(1);
});
