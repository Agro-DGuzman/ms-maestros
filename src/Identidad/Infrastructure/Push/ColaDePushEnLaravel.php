<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Push;

use Identidad\Application\Contracts\ColaDePush;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Sesiones\IdDeSesion;

final readonly class ColaDePushEnLaravel implements ColaDePush
{
    public function encolar(IdDeSesion $sesion, AvisoParaPush $aviso): void
    {
        EnviarPushJob::dispatch($sesion->value(), $aviso->aArreglo());
    }
}
