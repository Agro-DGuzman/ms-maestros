<?php

declare(strict_types=1);

namespace BackOffice\Domain\Operadores;

use InvalidArgumentException;

/**
 * El `oid` del token de Entra. Es el único identificador del operador que
 * sobrevive a que TI le cambie el nombre o el correo.
 */
final readonly class IdDeOperador
{
    private function __construct(public string $valor) {}

    public static function desdeOid(string $oid): self
    {
        $limpio = trim($oid);

        if ($limpio === '') {
            throw new InvalidArgumentException('OPERADOR_SIN_OID');
        }

        return new self($limpio);
    }

    public function esIgualA(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }
}
