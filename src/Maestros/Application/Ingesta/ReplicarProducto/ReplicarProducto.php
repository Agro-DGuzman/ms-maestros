<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ReplicarProducto;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ProductoIngresado;

/**
 * El `POST` y el `PUT` de la ingesta de productos. No lleva `momento` como el
 * socio: nada de lo que escribe depende de cuándo llegó el envío, porque la
 * baja la pone solo el `DELETE`.
 */
final readonly class ReplicarProducto implements EscrituraDeMaquina, Request, RequiereTransaccion
{
    public function __construct(
        public OperacionDeIngesta $operacion,
        public ?string $itemCodeDeLaRuta,
        public ProductoIngresado $producto,
    ) {}
}
