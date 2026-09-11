<?php

declare(strict_types=1);

namespace Maestros\Application\Alcance;

use Core\Results\Error;

final class AlcanceErrors
{
    /**
     * Nunca 404 ni lista vacía: filtrar en silencio deja un permiso mal
     * configurado indistinguible de un socio inexistente.
     */
    public static function accesoDenegado(): Error
    {
        return Error::failure('ACCESO_DENEGADO', 'El socio solicitado no está en su alcance');
    }
}
