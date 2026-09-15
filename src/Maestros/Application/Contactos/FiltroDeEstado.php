<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

enum FiltroDeEstado: string
{
    case Todas = 'todas';
    case Habilitadas = 'habilitadas';
    case NoHabilitadas = 'no_habilitadas';
}
