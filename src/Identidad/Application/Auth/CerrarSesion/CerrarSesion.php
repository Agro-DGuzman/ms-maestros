<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\CerrarSesion;

use Core\Contracts\Request;

final readonly class CerrarSesion implements Request
{
    public function __construct(public string $refreshToken) {}
}
