<?php

declare(strict_types=1);

namespace BackOffice\Domain\Operadores;

/**
 * La persona de Agropartners que concede o quita el acceso a la App.
 * No se persiste: vive en la sesión y se copia a cada asiento de bitácora.
 */
final readonly class Operador
{
    public function __construct(
        public IdDeOperador $id,
        public string $nombre,
        public string $correo,
    ) {}
}
