<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use App\Persistence\InstanteUtc;
use DateTimeImmutable;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;
use Maestros\Application\Ingesta\RegistroDeEventos;

/**
 * Un insert, no un upsert: dos procesos con el mismo evento chocan en la
 * clave, la segunda transacción se deshace, y su mensaje vuelve a entregarse
 * para salir como duplicado. Copiado de ms-comercial.
 */
final class EloquentRegistroDeEventos implements RegistroDeEventos
{
    public function yaProcesado(string $eventoId): bool
    {
        return EventoProcesadoRecord::query()->whereKey($eventoId)->exists();
    }

    public function registrar(string $eventoId, string $tipo, ResultadoDelEvento $resultado): void
    {
        EventoProcesadoRecord::query()->insert([
            'evento_id' => $eventoId,
            'tipo' => $tipo,
            'resultado' => $resultado->value,
            'procesado_en' => InstanteUtc::paraGuardar(new DateTimeImmutable),
        ]);
    }
}
