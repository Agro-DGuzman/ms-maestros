<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Eventos\EventoSaliente;
use Maestros\Application\Eventos\SalidaDeEventos;
use Ramsey\Uuid\Uuid;

uses(RefreshDatabase::class);

function eventoParaElOutbox(string $creadoEn, string $razonSocial = 'Agro Prueba SRL'): EventoSaliente
{
    $eventoId = Uuid::uuid4()->toString();

    return new EventoSaliente($eventoId, 'maestros.socio.actualizado', [
        'eventoId' => $eventoId,
        'tipo' => 'maestros.socio.actualizado',
        'datos' => ['cardCode' => '10057', 'razonSocial' => $razonSocial],
    ], new DateTimeImmutable($creadoEn));
}

function filaDelOutbox(string $eventoId): ?object
{
    return DB::table('maestros_evento_saliente')->where('evento_id', $eventoId)->first();
}

it('registra un evento y lo devuelve pendiente con el sobre intacto', function () {
    $salida = app(SalidaDeEventos::class);
    $evento = eventoParaElOutbox('2026-10-29T14:00:00.123456Z', 'Vargas Peña SRL · Lote 14');

    $salida->registrar($evento);
    $pendiente = $salida->pendientes(50)[0];

    expect($pendiente->eventoId)->toBe($evento->eventoId)
        ->and($pendiente->tipo)->toBe('maestros.socio.actualizado')
        ->and($pendiente->sobre)->toBe($evento->sobre)
        ->and($pendiente->creadoEn->format('Y-m-d H:i:s.u'))->toBe('2026-10-29 14:00:00.123456')
        // Sin escapes: en la base se lee igual que en la App.
        ->and((string) filaDelOutbox($evento->eventoId)?->sobre)->toContain('Vargas Peña SRL · Lote 14');
});

it('devuelve las pendientes por orden de llegada y respeta el limite', function () {
    $salida = app(SalidaDeEventos::class);
    $tercero = eventoParaElOutbox('2026-10-29T14:00:02Z');
    $primero = eventoParaElOutbox('2026-10-29T14:00:00Z');
    $segundo = eventoParaElOutbox('2026-10-29T14:00:01Z');

    foreach ([$tercero, $primero, $segundo] as $evento) {
        $salida->registrar($evento);
    }

    expect(array_map(static fn (EventoSaliente $e): string => $e->eventoId, $salida->pendientes(2)))
        ->toBe([$primero->eventoId, $segundo->eventoId]);
});

it('una publicada ya no esta pendiente', function () {
    $salida = app(SalidaDeEventos::class);
    $evento = eventoParaElOutbox('2026-10-29T14:00:00Z');
    $salida->registrar($evento);

    $salida->marcarPublicado($evento->eventoId);

    expect($salida->pendientes(50))->toBe([])
        ->and(filaDelOutbox($evento->eventoId)?->publicado_en)->not->toBeNull();
});

it('sumarIntento cuenta y la deja pendiente', function () {
    $salida = app(SalidaDeEventos::class);
    $evento = eventoParaElOutbox('2026-10-29T14:00:00Z');
    $salida->registrar($evento);

    $salida->sumarIntento($evento->eventoId);

    expect((int) filaDelOutbox($evento->eventoId)?->intentos)->toBe(1)
        ->and($salida->pendientes(50))->toHaveCount(1);
});
