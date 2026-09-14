<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\RenovarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

final readonly class RenovarSesion implements Request, RequiereTransaccion
{
    public function __construct(public string $refreshToken) {}
}
