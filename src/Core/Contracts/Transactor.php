<?php

declare(strict_types=1);

namespace Core\Contracts;

use Closure;
use Core\Results\Result;

/**
 * Corre un trabajo dentro de una transacción y lo deshace si lanza.
 *
 * Existe para que `TransaccionBehavior` no tenga que nombrar a Eloquent:
 * `Core/` no referencia `Illuminate\`, así que el que sabe de conexiones es
 * el adaptador del host.
 */
interface Transactor
{
    /** @param  Closure(): Result  $trabajo */
    public function transaction(Closure $trabajo): Result;
}
