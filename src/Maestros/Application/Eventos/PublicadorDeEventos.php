<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

/**
 * El topic `maestros-eventos`, visto desde el consumidor que vacía el outbox
 * (EM6).
 */
interface PublicadorDeEventos
{
    /**
     * Lanza si el bus no confirmó la publicación: la fila sigue pendiente.
     *
     * @param  array<array-key, mixed>  $sobre
     */
    public function publicar(array $sobre): void;
}
