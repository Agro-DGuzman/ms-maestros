<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/** Qué pasó con un envío que se aceptó: va al log, para cruzarlo con el del Sincronizador. */
enum ResultadoDeIngesta: string
{
    case Aplicado = 'aplicado';
    case IgnoradoPorViejo = 'ignorado-por-viejo';
    case DadoDeBaja = 'dado-de-baja';
    case NoConservado = 'no-conservado';
}
