<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

enum OperacionDeIngesta
{
    case Crear;
    case Reemplazar;
}
