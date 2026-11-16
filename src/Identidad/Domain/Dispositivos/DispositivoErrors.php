<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

use Core\Results\Error;

final class DispositivoErrors
{
    public static function tokenPushInvalido(): Error
    {
        return Error::validation('TOKEN_PUSH_INVALIDO', 'El token de notificaciones no es válido');
    }

    /** Esa persona no tiene una sesión abierta con esa instalación (NP18). */
    public static function noEncontrado(): Error
    {
        return Error::notFound('DISPOSITIVO_NO_ENCONTRADO', 'No hay una sesión abierta en este dispositivo.');
    }
}
