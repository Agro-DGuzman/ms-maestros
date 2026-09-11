<?php

declare(strict_types=1);

namespace Core\Mediator\Behaviors;

use Closure;
use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\PipelineBehavior;
use Core\Contracts\Request;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Alcance\AlcanceErrors;
use Maestros\Application\Alcance\ResolutorDeAlcance;

final readonly class AlcanceBehavior implements PipelineBehavior
{
    public function __construct(private ResolutorDeAlcance $resolutor) {}

    public function handle(Request $peticion, Closure $siguiente): Result
    {
        if (! $peticion instanceof ConAlcanceDeSocio) {
            return $siguiente($peticion);
        }

        $socio = $peticion->cardCode();

        if ($socio === null) {
            return $siguiente($peticion);
        }

        if (! $this->resolutor->alcanza($peticion->persona(), $socio)) {
            return ResultWithValue::failure(AlcanceErrors::accesoDenegado());
        }

        return $siguiente($peticion);
    }
}
