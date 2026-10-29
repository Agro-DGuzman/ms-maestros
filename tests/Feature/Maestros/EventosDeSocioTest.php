<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\EventoSaliente;
use Maestros\Application\Eventos\SalidaDeEventos;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocio;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Application\Ingesta\SocioIngresado;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Infrastructure\Persistence\SocioRecord;
use Tests\Soporte\Contrato;
use Tests\Soporte\CuerpoDeIngesta;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
});

function replicarConEvento(SocioIngresado $socio, OperacionDeIngesta $operacion = OperacionDeIngesta::Crear): Result
{
    return app(Mediator::class)->send(new ReplicarSocio(
        $operacion,
        $operacion === OperacionDeIngesta::Reemplazar ? $socio->cardCode : null,
        $socio,
        new DateTimeImmutable('2026-10-05T12:30:00Z'),
    ));
}

/**
 * Los sobres del outbox, en orden, y cada uno validado contra el contrato.
 *
 * @return list<array<string, mixed>>
 */
function sobresDelOutbox(): array
{
    $sobres = [];

    foreach (DB::table('maestros_evento_saliente')->orderBy('creado_en')->get() as $fila) {
        $sobre = json_decode((string) $fila->sobre, true, 64, JSON_THROW_ON_ERROR);
        expect(Contrato::diferenciasDeEsquema($sobre, 'eventos-de-dominio-v1.yaml', 'SocioActualizado'))->toBe([]);
        $sobres[] = $sobre;
    }

    return $sobres;
}

it('crear deja un evento con la linea y el grupo guardado', function () {
    replicarConEvento(CuerpoDeIngesta::socio());

    $sobres = sobresDelOutbox();

    expect($sobres)->toHaveCount(1)
        ->and($sobres[0]['datos']['cardCode'])->toBe('C-900001')
        ->and($sobres[0]['datos']['lineaDeCredito'])->toBe('50000.00')
        ->and($sobres[0]['datos']['grupoEconomico']['codigo'])->toBe('GRP-900')
        ->and($sobres[0]['datos']['grupoEconomico']['segmento'])->toBe('Agroindustrial')
        ->and($sobres[0]['datos']['deBaja'])->toBeFalse();
});

it('reemplazar deja otro evento con el ocurridoEn del cuerpo', function () {
    replicarConEvento(CuerpoDeIngesta::socio());
    replicarConEvento(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T13:00:00Z'), OperacionDeIngesta::Reemplazar);

    $sobres = sobresDelOutbox();

    expect($sobres)->toHaveCount(2)
        ->and($sobres[1]['ocurridoEn'])->toBe('2026-10-05T13:00:00.000000Z');
});

it('el ocurridoEn es el vigenteDesde guardado, al segundo como la replica', function () {
    // El Sincronizador manda microsegundos; la réplica guarda al segundo, y la
    // foto del bus tiene que ser la guardada para ordenarse igual.
    replicarConEvento(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T12:00:00.866982+00:00'));

    expect(sobresDelOutbox()[0]['ocurridoEn'])->toBe('2026-10-05T12:00:00.000000Z');
});

it('un cuerpo viejo no deja evento', function () {
    replicarConEvento(CuerpoDeIngesta::socio());
    replicarConEvento(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T11:00:00Z'), OperacionDeIngesta::Reemplazar);

    expect(sobresDelOutbox())->toHaveCount(1);
});

it('un proveedor nuevo no deja evento', function () {
    replicarConEvento(CuerpoDeIngesta::socio(tipoSap: 'S'));

    expect(sobresDelOutbox())->toBe([]);
});

it('un socio que pasa a proveedor deja su baja', function () {
    replicarConEvento(CuerpoDeIngesta::socio());
    replicarConEvento(CuerpoDeIngesta::socio(tipoSap: 'S', vigenteDesde: '2026-10-05T13:00:00Z'), OperacionDeIngesta::Reemplazar);

    $sobres = sobresDelOutbox();

    expect($sobres)->toHaveCount(2)
        ->and($sobres[1]['datos']['deBaja'])->toBeTrue();
});

it('el DELETE deja la baja con el momento de la baja', function () {
    replicarConEvento(CuerpoDeIngesta::socio());

    app(Mediator::class)->send(new DarDeBajaSocio(CodigoDeSocio::desde('C-900001'), new DateTimeImmutable('2026-10-05T15:00:00Z')));

    $sobres = sobresDelOutbox();

    expect($sobres)->toHaveCount(2)
        ->and($sobres[1]['datos']['deBaja'])->toBeTrue()
        ->and($sobres[1]['ocurridoEn'])->toBe('2026-10-05T15:00:00.000000Z');
});

it('la baja de un socio sin linea se guarda sin evento', function () {
    $resultado = app(Mediator::class)->send(new DarDeBajaSocio(CodigoDeSocio::desde('C-004871'), new DateTimeImmutable('2026-10-05T15:00:00Z')));

    expect($resultado->isSuccess)->toBeTrue()
        ->and(SocioRecord::query()->find('C-004871')?->dado_de_baja_el)->not->toBeNull()
        ->and(sobresDelOutbox())->toBe([]);
});

it('si el outbox falla no queda el socio', function () {
    app()->instance(SalidaDeEventos::class, new class implements SalidaDeEventos
    {
        public function registrar(EventoSaliente $evento): void
        {
            throw new RuntimeException('el outbox no responde');
        }

        public function pendientes(int $limite): array
        {
            return [];
        }

        public function marcarPublicado(string $eventoId): void {}

        public function sumarIntento(string $eventoId): void {}
    });

    expect(fn () => replicarConEvento(CuerpoDeIngesta::socio()))->toThrow(RuntimeException::class, 'el outbox no responde')
        ->and(SocioRecord::query()->find('C-900001'))->toBeNull();
});
