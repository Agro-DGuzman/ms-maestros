<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

enum EstadoDeEnvio
{
    case Entregado;
    /** El token ya no sirve: se borra de la sesión y no se reintenta. */
    case TokenMuerto;
    /** No se reintenta ni se borra el token: puede ser un error nuestro o de configuración. */
    case Rechazado;
    case Reintentar;
}
