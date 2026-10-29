<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;

/** «¿Ya vi este mensaje?» (EM7): una entrega repetida del bus no cambia nada. */
interface RegistroDeEventos
{
    public function yaProcesado(string $eventoId): bool;

    public function registrar(string $eventoId, string $tipo, ResultadoDelEvento $resultado): void;
}
