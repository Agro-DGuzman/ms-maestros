<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocio;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\PublicarSocios\PublicarSocios;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Domain\Socios\CodigoDeSocio;
use Tests\Soporte\Contrato;
use Tests\Soporte\CuerpoDeIngesta;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);

    $mediator = app(Mediator::class);
    $momento = new DateTimeImmutable('2026-10-05T12:30:00Z');

    $mediator->send(new ReplicarSocio(OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(), $momento));
    $mediator->send(new ReplicarSocio(OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(cardCode: 'C-900002', activo: false, contactos: []), $momento));
    $mediator->send(new ReplicarSocio(OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(cardCode: 'C-900003', contactos: []), $momento));
    $mediator->send(new DarDeBajaSocio(CodigoDeSocio::desde('C-900003'), new DateTimeImmutable('2026-10-05T15:00:00Z')));

    // Lo que dejó la ingesta no cuenta: se mira solo lo que encola el envío.
    DB::table('maestros_evento_saliente')->delete();
});

it('encola un evento por socio con linea y sin baja', function () {
    $resultado = app(Mediator::class)->send(new PublicarSocios);

    $sobres = DB::table('maestros_evento_saliente')->get()
        ->map(static fn (object $fila): array => json_decode((string) $fila->sobre, true, 64, JSON_THROW_ON_ERROR))
        ->all();
    $cardCodes = array_map(static fn (array $s): string => $s['datos']['cardCode'], $sobres);
    sort($cardCodes);

    expect($resultado->value())->toBe(2)
        ->and($cardCodes)->toBe(['C-900001', 'C-900002']);

    foreach ($sobres as $sobre) {
        expect(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'SocioActualizado'))->toBe([])
            ->and($sobre['ocurridoEn'])->toBe('2026-10-05T12:00:00.000000Z');
    }
});

it('el comando dice cuantos encolo', function () {
    $this->artisan('eventos:publicar-socios')
        ->expectsOutput('Socios en la cola: 2.')
        ->assertExitCode(0);
});
