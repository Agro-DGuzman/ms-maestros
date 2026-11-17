<?php

declare(strict_types=1);

namespace Identidad\Application\Push\CambiarTokenPush;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * El token nuevo que Firebase le dio a una instalación, sin cerrar sesión
 * (NP18). La persona sale del token de acceso, nunca del cuerpo.
 */
final readonly class CambiarTokenPush implements Request, RequiereTransaccion
{
    public function __construct(
        public IdDePersona $persona,
        public string $instalacionId,
        public string $tokenPush,
    ) {}
}
