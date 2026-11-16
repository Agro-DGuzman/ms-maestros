<?php

declare(strict_types=1);

namespace App\Eventos;

use LogicException;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\OtrosEventos;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;

/**
 * Los tipos del bus que atiende Identidad. Este es el único lugar donde se
 * registran: traduce el sobre a una petición de Identidad y la despacha por
 * el mediator, así Maestros no la nombra e Identidad no conoce el sobre.
 */
final readonly class EventosDeIdentidad implements OtrosEventos
{
    public function atiende(string $tipo): bool
    {
        return false;
    }

    public function atender(Sobre $sobre): ResultadoDelEvento
    {
        throw new LogicException("EventosDeIdentidad no atiende {$sobre->tipo}");
    }
}
