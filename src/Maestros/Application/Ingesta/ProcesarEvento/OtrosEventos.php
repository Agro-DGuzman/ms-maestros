<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ProcesarEvento;

use Maestros\Application\Eventos\Sobre;

/**
 * Los tipos que llegan por el bus y no son de Maestros: los avisos y las
 * preferencias de push los atiende Identidad, y Maestros no puede nombrarla.
 * La implementación vive en el host, que conoce a los dos (NP17).
 *
 * Quien atiende un tipo valida sus datos y lanza si no los entiende: un
 * evento que no se entiende no se completa y termina en mensajes muertos.
 */
interface OtrosEventos
{
    public function atiende(string $tipo): bool;

    public function atender(Sobre $sobre): ResultadoDelEvento;
}
