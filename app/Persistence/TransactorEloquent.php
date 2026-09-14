<?php

declare(strict_types=1);

namespace App\Persistence;

use Closure;
use Core\Contracts\Transactor;
use Core\Results\Result;
use Illuminate\Database\ConnectionInterface;

/**
 * Vive en `app/` y no en un módulo porque es el host el que conoce el
 * framework, y porque la transacción la comparten `Maestros` e `Identidad`,
 * que no pueden referenciarse entre sí.
 */
final readonly class TransactorEloquent implements Transactor
{
    public function __construct(private ConnectionInterface $conexion) {}

    public function transaction(Closure $trabajo): Result
    {
        $resultado = $this->conexion->transaction($trabajo);
        assert($resultado instanceof Result);

        return $resultado;
    }
}
