<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Results\Error;

final class SocioErrors
{
    public static function codigoInvalido(string $codigo): Error
    {
        return Error::validation(
            'CODIGO_DE_SOCIO_INVALIDO',
            'El código de socio {codigo} no es válido',
            $codigo,
        );
    }

    public static function razonSocialVacia(): Error
    {
        return Error::validation('RAZON_SOCIAL_VACIA', 'La razón social no puede estar vacía');
    }

    public static function noEncontrado(string $codigo): Error
    {
        return Error::notFound('SOCIO_NO_ENCONTRADO', 'No existe el socio {codigo}', $codigo);
    }
}
