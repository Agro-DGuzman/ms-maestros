<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

enum FiltroDeEnlaces: string
{
    case Todos = 'todos';
    case SinImagen = 'sin-imagen';
    /** Los que no tienen ninguno de los tres documentos. */
    case SinDocumentos = 'sin-documentos';
}
