<?php

declare(strict_types=1);

namespace BackOffice\Application\Catalogo\ActualizarDescripcion;

use BackOffice\Domain\Operadores\Operador;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

/**
 * Transaccional: el cambio en el catálogo y su asiento se confirman juntos, o
 * ninguno. Una descripción cambiada sin su asiento es un cambio que nadie hizo.
 */
final readonly class ActualizarDescripcion implements Request, RequiereTransaccion
{
    public function __construct(
        public int $idProducto,
        public ?string $descripcion,
        public Operador $operador,
        public string $direccionIp,
    ) {}
}
