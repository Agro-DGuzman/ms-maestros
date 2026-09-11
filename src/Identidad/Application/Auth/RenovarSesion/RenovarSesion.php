<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\RenovarSesion;

use Core\Contracts\Request;

final readonly class RenovarSesion implements Request
{
    public function __construct(public string $refreshToken) {}
}
