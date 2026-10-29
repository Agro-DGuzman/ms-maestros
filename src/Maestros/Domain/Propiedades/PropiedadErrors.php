<?php

declare(strict_types=1);

namespace Maestros\Domain\Propiedades;

use Core\Results\Error;

final class PropiedadErrors
{
    public static function idInvalido(string $id): Error
    {
        return Error::validation('PROPIEDAD_ID_INVALIDO', 'El id de propiedad «{id}» no es válido', $id);
    }

    public static function nombreInvalido(string $nombre): Error
    {
        return Error::validation('PROPIEDAD_NOMBRE_INVALIDO', 'El nombre de propiedad «{nombre}» no es válido', $nombre);
    }

    public static function estadoInvalido(string $estado): Error
    {
        return Error::validation('PROPIEDAD_ESTADO_INVALIDO', 'El estado «{estado}» no es activo ni inactivo', $estado);
    }
}
