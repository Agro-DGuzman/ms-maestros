<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
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
    // 43 = 3 grupos + 8 socios + 32 contactos del archivo de semilla.
    $this->artisan('maestros:importar', ['archivo' => $archivo])
        ->expectsOutputToContain('Omitidos por ser más viejos: 43')
        ->assertExitCode(0);
});

it('un contacto que estaba y no viene en el archivo queda dado de baja', function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $sinUno = tempnam(sys_get_temp_dir(), 'imp').'.json';
    $datos = json_decode((string) file_get_contents(database_path('semillas/maestros-ejemplo.json')), true);
    $datos['vigenteDesde'] = '2030-01-01T00:00:00Z';
    $datos['contactos'] = array_values(array_filter($datos['contactos'], fn (array $c): bool => $c['id'] !== 'p-a1b2c301'));
    file_put_contents($sinUno, json_encode($datos));

    $this->artisan('maestros:importar', ['archivo' => $sinUno])->assertExitCode(0);

    $contactos = app(ContactoRepository::class);

    expect($contactos->find(IdDePersona::desde('p-a1b2c301'))?->dadoDeBajaEl())->not->toBeNull()
        ->and($contactos->find(IdDePersona::desde('p-8f2b1c40'))?->dadoDeBajaEl())->toBeNull();
});

it('un archivo sin la lista de contactos no da de baja a nadie', function () {
    // Un archivo parcial (solo socios) no dice nada de los contactos: tomarlo
    // como «ninguno sigue en SAP» los daría de baja a todos.
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $soloSocios = tempnam(sys_get_temp_dir(), 'imp').'.json';
    file_put_contents($soloSocios, json_encode([
        'vigenteDesde' => '2030-01-01T00:00:00Z',
        'socios' => [['cardCode' => 'C-004871', 'razonSocial' => 'Sebastian Monasterio', 'grupoId' => 'GRP-014']],
    ]));

    $this->artisan('maestros:importar', ['archivo' => $soloSocios])->assertExitCode(0);

    expect(app(ContactoRepository::class)->find(IdDePersona::desde('p-8f2b1c40'))?->dadoDeBajaEl())->toBeNull();
});

it('un contacto omitido por viejo pero presente en el archivo no se da de baja', function () {
    // La segunda corrida omite todo por no ser más nuevo. Omitir no es «ya no
    // está en SAP»: si la baja mirara solo lo guardado, daría de baja a todos.
    $archivo = database_path('semillas/maestros-ejemplo.json');

    $this->artisan('maestros:importar', ['archivo' => $archivo])->assertExitCode(0);
    $this->artisan('maestros:importar', ['archivo' => $archivo])
        ->expectsOutputToContain('Omitidos por ser más viejos')
        ->assertExitCode(0);

    expect(app(ContactoRepository::class)->find(IdDePersona::desde('p-8f2b1c40'))?->dadoDeBajaEl())->toBeNull();
});
