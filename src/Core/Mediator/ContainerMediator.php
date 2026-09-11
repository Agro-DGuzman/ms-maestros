<?php

declare(strict_types=1);

namespace Core\Mediator;

use Closure;
use Core\Contracts\Mediator;
use Core\Contracts\PipelineBehavior;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use LogicException;
use Psr\Container\ContainerInterface;

/** Reemplazo de Pipelinr: handlers resueltos del contenedor, behaviors encadenados. */
final class ContainerMediator implements Mediator
{
    /**
     * @param  array<class-string<Request>, class-string<RequestHandler>>  $handlers
     * @param  list<class-string<PipelineBehavior>>  $behaviors
     */
    public function __construct(
        private readonly ContainerInterface $contenedor,
        private readonly array $handlers,
        private readonly array $behaviors,
    ) {}

    public function send(Request $peticion): Result
    {
        $handler = $this->handlers[$peticion::class] ?? throw new LogicException(
            sprintf('No hay handler registrado para %s', $peticion::class),
        );

        $cadena = function (Request $p) use ($handler): Result {
            $instancia = $this->contenedor->get($handler);
            assert($instancia instanceof RequestHandler);

            return $instancia->handle($p);
        };

        foreach (array_reverse($this->behaviors) as $behavior) {
            $siguiente = $cadena;
            $cadena = function (Request $p) use ($behavior, $siguiente): Result {
                $instancia = $this->contenedor->get($behavior);
                assert($instancia instanceof PipelineBehavior);

                return $instancia->handle($p, Closure::fromCallable($siguiente));
            };
        }

        return $cadena($peticion);
    }
}
