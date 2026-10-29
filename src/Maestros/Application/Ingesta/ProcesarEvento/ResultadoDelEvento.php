<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ProcesarEvento;

/** Lo que queda en `maestros.evento_procesado`: responde «¿por qué no se ve el cambio?» sin ir al bus. */
enum ResultadoDelEvento: string
{
    case Aplicado = 'aplicado';
    case IgnoradoPorViejo = 'ignorado-por-viejo';
    case Duplicado = 'duplicado';
}
