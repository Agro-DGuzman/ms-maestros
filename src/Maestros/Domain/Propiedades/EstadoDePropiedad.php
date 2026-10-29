<?php

declare(strict_types=1);

namespace Maestros\Domain\Propiedades;

/** Tal cual lo manda Appgronomy y lo reenvía ms-comercial (ED6). Para la App existe solo la activa. */
enum EstadoDePropiedad: string
{
    case Activo = 'activo';
    case Inactivo = 'inactivo';
}
