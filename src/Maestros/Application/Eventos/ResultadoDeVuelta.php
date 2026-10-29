<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

enum ResultadoDeVuelta
{
    case BaseCaida;
    /** El bus o la red fallaron al recibir o al completar: se reintenta con espera. */
    case BusNoResponde;
    case SinMensajes;
    case Completado;
    case Fallido;
}
