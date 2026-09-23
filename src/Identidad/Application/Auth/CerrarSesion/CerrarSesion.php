<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\CerrarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Cierra la sesión de un dispositivo, identificada por su refresh token, por
 * su instalación, o por las dos. Las dos son opcionales porque así lo dice el
 * contrato: sin ninguna no hay nada que cerrar, y responde igual.
 */
final readonly class CerrarSesion implements Request, RequiereTransaccion
{
    public function __construct(
        public IdDePersona $persona,
        public ?string $refreshToken,
        public ?string $instalacionId,
    ) {}
}
