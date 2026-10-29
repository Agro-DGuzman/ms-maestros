<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

/**
 * La suscripción, vista desde el consumidor. No hay "abandonar": un mensaje
 * que no se completa se deja vencer, y el vencimiento del bloqueo es el
 * retroceso natural antes del siguiente intento.
 */
interface BuzonDeEntrada
{
    /**
     * `null` si no llegó nada durante la espera.
     *
     * @param  int|null  $esperaSegundos  `null`: la configurada
     */
    public function recibir(?int $esperaSegundos = null): ?MensajeRecibido;

    public function completar(MensajeRecibido $mensaje): void;
}
