<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ConcederAcceso;

use BackOffice\Domain\Operadores\Operador;
use Core\Contracts\Request;

/**
 * El identificador de la persona viaja como `string`: el back-office registra
 * lo que pasó, no valida a quién le pasó. Quien valida es el caso de uso de
 * Identidad que se despacha adentro.
 */
final readonly class ConcederAcceso implements Request
{
    public function __construct(
        public string $idDePersona,
        public Operador $operador,
        public string $direccionIp,
    ) {}
}
