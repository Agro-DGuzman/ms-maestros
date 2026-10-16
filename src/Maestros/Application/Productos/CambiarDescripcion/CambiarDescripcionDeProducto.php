<?php

declare(strict_types=1);

namespace Maestros\Application\Productos\CambiarDescripcion;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

/**
 * Caso de uso aparte y no un campo más de `CambiarEnlacesDeProducto`:
 * `CampoDeEnlace` arma el formulario y valida URLs, y la descripción no es un
 * enlace.
 */
final readonly class CambiarDescripcionDeProducto implements Request, RequiereTransaccion
{
    public function __construct(
        public int $idProducto,
        public ?string $descripcion,
    ) {}
}
