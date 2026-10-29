<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use App\Persistence\InstanteUtc;
use DateTimeImmutable;
use JsonException;
use Maestros\Application\Eventos\EventoSaliente;
use Maestros\Application\Eventos\SalidaDeEventos;
use RuntimeException;

final class EloquentSalidaDeEventos implements SalidaDeEventos
{
    public function registrar(EventoSaliente $evento): void
    {
        EventoSalienteRecord::query()->insert([
            'evento_id' => $evento->eventoId,
            'tipo' => $evento->tipo,
            // Sin escapes: «Peña» se lee igual en la base que en la App.
            'sobre' => json_encode($evento->sobre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'creado_en' => InstanteUtc::paraGuardar($evento->creadoEn),
            'publicado_en' => null,
            'intentos' => 0,
        ]);
    }

    public function pendientes(int $limite): array
    {
        return array_values(
            EventoSalienteRecord::query()
                ->whereNull('publicado_en')
                ->orderBy('creado_en')
                ->limit($limite)
                ->get()
                ->map(static fn (EventoSalienteRecord $r): EventoSaliente => self::armar($r))
                ->all(),
        );
    }

    public function marcarPublicado(string $eventoId): void
    {
        EventoSalienteRecord::query()->whereKey($eventoId)->update(['publicado_en' => InstanteUtc::paraGuardar(new DateTimeImmutable)]);
    }

    public function sumarIntento(string $eventoId): void
    {
        EventoSalienteRecord::query()->whereKey($eventoId)->increment('intentos');
    }

    private static function armar(EventoSalienteRecord $record): EventoSaliente
    {
        try {
            $sobre = json_decode($record->sobre, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("El sobre del evento {$record->evento_id} no es JSON: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($sobre)) {
            throw new RuntimeException("El sobre del evento {$record->evento_id} no es un objeto");
        }

        /** @var array<string, mixed> $sobre */
        return new EventoSaliente(
            // pdo_sqlsrv devuelve el uniqueidentifier en mayúsculas; el sobre lo lleva en minúsculas.
            strtolower($record->evento_id),
            $record->tipo,
            $sobre,
            InstanteUtc::leido($record->creado_en),
        );
    }
}
