<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\VerificadorDeToken;
use Maestros\Domain\Contactos\IdDePersona;

final class VerificadorFalso implements VerificadorDeToken
{
    public function __construct(private readonly ?string $persona) {}

    public function verificar(string $jwt): ?IdDePersona
    {
        return $jwt === 'token-bueno' && $this->persona !== null
            ? IdDePersona::desde($this->persona)
            : null;
    }
}
