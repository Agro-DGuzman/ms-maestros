<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

/**
 * El outbox (EM1). Se escribe en la misma transacción que el socio, y el
 * consumidor lo vacía en cada vuelta (EM6).
 */
interface SalidaDeEventos
{
    public function registrar(EventoSaliente $evento): void;

    /**
     * Las que todavía no se publicaron, por orden de llegada.
     *
     * @return list<EventoSaliente>
     */
    public function pendientes(int $limite): array;

    /** El instante lo pone el adaptador: así la aplicación no necesita un reloj. */
    public function marcarPublicado(string $eventoId): void;

    public function sumarIntento(string $eventoId): void;
}
