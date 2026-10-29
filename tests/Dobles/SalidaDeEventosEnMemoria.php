<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Maestros\Application\Eventos\EventoSaliente;
use Maestros\Application\Eventos\SalidaDeEventos;

/** El outbox en memoria: recuerda qué se registró, qué se marcó publicado y cuántos intentos sumó cada uno. */
final class SalidaDeEventosEnMemoria implements SalidaDeEventos
{
    /** @var list<EventoSaliente> */
    public array $registrados = [];

    /** @var array<string, true> */
    public array $publicados = [];

    /** @var array<string, int> */
    public array $intentos = [];

    public static function con(EventoSaliente ...$eventos): self
    {
        $salida = new self;

        foreach ($eventos as $evento) {
            $salida->registrar($evento);
        }

        return $salida;
    }

    public function registrar(EventoSaliente $evento): void
    {
        $this->registrados[] = $evento;
    }

    public function pendientes(int $limite): array
    {
        $pendientes = array_values(array_filter(
            $this->registrados,
            fn (EventoSaliente $e): bool => ! isset($this->publicados[$e->eventoId]),
        ));

        usort($pendientes, static fn (EventoSaliente $a, EventoSaliente $b): int => $a->creadoEn <=> $b->creadoEn);

        return array_slice($pendientes, 0, $limite);
    }

    public function marcarPublicado(string $eventoId): void
    {
        $this->publicados[$eventoId] = true;
    }

    public function sumarIntento(string $eventoId): void
    {
        $this->intentos[$eventoId] = ($this->intentos[$eventoId] ?? 0) + 1;
    }
}
