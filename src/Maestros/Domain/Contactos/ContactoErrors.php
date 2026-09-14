<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Results\Error;

final class ContactoErrors
{
    public static function celularInvalido(string $numero): Error
    {
        return Error::validation(
            'CELULAR_INVALIDO',
            'El celular {numero} no es un número móvil boliviano válido',
            $numero,
        );
    }

    public static function idInvalido(): Error
    {
        return Error::validation('PERSONA_INVALIDA', 'El identificador de persona no puede estar vacío');
    }

    public static function noEncontrado(string $id): Error
    {
        return Error::notFound(
            'CONTACTO_NO_ENCONTRADO',
            'No existe la persona de contacto {id}',
            $id,
        );
    }
}
