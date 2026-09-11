<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\IdDePersona;

interface VerificadorDeToken
{
    public function verificar(string $jwt): ?IdDePersona;
}
