<?php

declare(strict_types=1);

namespace BackOffice\Application\Catalogo\ActualizarEnlaces;

use BackOffice\Domain\Operadores\Operador;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

/**
 * Transaccional: el cambio en el catálogo y sus asientos se confirman juntos,
 * o ninguno. Un enlace cambiado sin su asiento es un cambio que nadie hizo.
 */
final readonly class ActualizarEnlaces implements Request, RequiereTransaccion
{
    public function __construct(
        public int $idProducto,
        public ?string $imagen,
        public ?string $fichaTecnica,
        public ?string $hojaDeSeguridad,
        public ?string $registroSanitario,
        public Operador $operador,
        public string $direccionIp,
    ) {}
}
