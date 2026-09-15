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

    /**
     * Mismo código que `celularInvalido`, otro destinatario: aquel le habla al
     * socio que tecleó mal su número; este al operador, que no puede corregir
     * nada desde la pantalla. Por eso el mensaje dice qué hacer y dónde.
     */
    public static function celularNoUtilizable(): Error
    {
        return Error::validation(
            'CELULAR_INVALIDO',
            'Esta persona no tiene un celular con el que pueda entrar a la App. '.
            'Corregilo en SAP y volvé a importar.',
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
