<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ProcesarEvento;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Application\Eventos\Sobre;

/**
 * Un mensaje del bus (EM7). Transaccional: la propiedad y el registro del
 * evento se confirman juntos, o un evento aplicado sin registrar se aplicaría
 * de nuevo en la entrega siguiente.
 */
final readonly class ProcesarEvento implements EscrituraDeMaquina, Request, RequiereTransaccion
{
    public function __construct(public Sobre $sobre) {}
}
