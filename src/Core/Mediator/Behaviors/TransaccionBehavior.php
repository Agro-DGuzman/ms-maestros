<?php

declare(strict_types=1);

namespace Core\Mediator\Behaviors;

use Closure;
use Core\Contracts\PipelineBehavior;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Core\Contracts\Transactor;
use Core\Results\Result;

/**
 * La transacción vive acá y no en un UnitOfWork: con Eloquent los repositorios
 * escriben apenas se los llama, así que el único punto que envuelve a todas
 * las escrituras de un caso de uso es el pipeline.
 *
 * Deshace solo si el handler lanza. Un `Result` fallido **confirma**: es una
 * salida deliberada del caso de uso, y lo que escribió antes de decidirla es
 * parte de la decisión. El caso que lo obliga es el contador de intentos
 * fallidos del desafío de ingreso, que se persiste justo antes de devolver
 * `CODIGO_INVALIDO`; si el fallo deshiciera, el límite de cinco intentos no
 * se aplicaría nunca.
 */
final readonly class TransaccionBehavior implements PipelineBehavior
{
    public function __construct(private Transactor $transactor) {}

    public function handle(Request $peticion, Closure $siguiente): Result
    {
        if (! $peticion instanceof RequiereTransaccion) {
            return $siguiente($peticion);
        }

        return $this->transactor->transaction(
            static fn (): Result => $siguiente($peticion),
        );
    }
}
