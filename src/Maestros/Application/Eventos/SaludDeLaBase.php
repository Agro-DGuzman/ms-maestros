<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

interface SaludDeLaBase
{
    /**
     * Si se puede escribir un evento. Una base caída —o sin migrar— no puede,
     * y recibir mensajes en ese estado solo gasta sus cinco intentos.
     */
    public function responde(): bool;
}
