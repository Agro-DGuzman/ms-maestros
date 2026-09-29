<?php

declare(strict_types=1);

namespace Maestros\Application\Productos\CambiarEnlaces;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

/**
 * Los cuatro enlaces como los dejó la persona en el formulario: una cadena
 * vacía o null quita el enlace.
 */
final readonly class CambiarEnlacesDeProducto implements Request, RequiereTransaccion
{
    public function __construct(
        public int $idProducto,
        public ?string $imagen,
        public ?string $fichaTecnica,
        public ?string $hojaDeSeguridad,
        public ?string $registroSanitario,
    ) {}
}
