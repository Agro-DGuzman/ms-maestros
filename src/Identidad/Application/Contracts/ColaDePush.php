<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Sesiones\IdDeSesion;

/**
 * Un trabajo por teléfono (NP14), que lleva la sesión y el aviso pero nunca
 * el token: el que se usa es el que la sesión tenga al mandar (NP22).
 */
interface ColaDePush
{
    public function encolar(IdDeSesion $sesion, AvisoParaPush $aviso): void;
}
