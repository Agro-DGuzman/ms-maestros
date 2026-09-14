<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
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

it('no pisa un dato nuevo con una carga mas vieja', function () {
    $nuevo = tempnam(sys_get_temp_dir(), 'imp').'.json';
    file_put_contents($nuevo, json_encode([
        'vigenteDesde' => '2026-09-14T12:00:00Z',
        'grupos' => [['id' => 'GRP-014', 'nombre' => 'Grupo Monasterio']],
        'socios' => [['cardCode' => 'C-004871', 'razonSocial' => 'Razon NUEVA', 'grupoId' => 'GRP-014']],
    ]));

    $viejo = tempnam(sys_get_temp_dir(), 'imp').'.json';
    file_put_contents($viejo, json_encode([
        'vigenteDesde' => '2026-01-01T00:00:00Z',
        'grupos' => [['id' => 'GRP-014', 'nombre' => 'Grupo Monasterio']],
        'socios' => [['cardCode' => 'C-004871', 'razonSocial' => 'Razon VIEJA', 'grupoId' => 'GRP-014']],
    ]));

    $this->artisan('maestros:importar', ['archivo' => $nuevo])->assertExitCode(0);
    $this->artisan('maestros:importar', ['archivo' => $viejo])->assertExitCode(0);

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-004871'))->razonSocial()->texto())
        ->toBe('Razon NUEVA');
});

it('informa cuantas filas se omitieron por viejas', function () {
    $archivo = database_path('semillas/maestros-ejemplo.json');

    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);

    // La segunda corrida tiene la misma marca: nada es más nuevo, todo se omite.
    $this->artisan('maestros:importar', ['archivo' => $archivo])
        ->expectsOutputToContain('Omitidos por ser más viejos: 5')
        ->assertExitCode(0);
});
