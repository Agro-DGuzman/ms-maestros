<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Results\Error;

final class SesionErrors
{
    /**
     * Desconocido, ya rotado, de una sesión cerrada o vencido en el proveedor:
     * para la App todos significan lo mismo, volver a pedir el código.
     */
    public static function refreshInvalido(): Error
    {
        return Error::failure('REFRESH_TOKEN_INVALIDO', 'La sesión expiró. Vuelve a iniciar sesión con tu celular.');
    }
}
