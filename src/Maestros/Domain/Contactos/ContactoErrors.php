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

    /** Al operador, que es quien intenta darle acceso: dice qué corregir y dónde. */
    public static function numeroNoIngresa(MotivoDeNoIngreso $motivo): Error
    {
        return match ($motivo) {
            MotivoDeNoIngreso::SinContactos => Error::validation(
                'NUMERO_SIN_CONTACTOS',
                'Ningún contacto activo en SAP tiene este número.',
            ),
            MotivoDeNoIngreso::SoloSinGrupo => Error::validation(
                'NUMERO_SOLO_SIN_GRUPO',
                'El número solo es contacto de socios sin grupo económico: no puede ingresar.',
            ),
            MotivoDeNoIngreso::VariosGrupos => Error::validation(
                'NUMERO_EN_VARIOS_GRUPOS',
                'El número es contacto en socios de más de un grupo económico: hay que corregirlo en SAP.',
            ),
        };
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
