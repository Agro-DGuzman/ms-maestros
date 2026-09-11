<?php

declare(strict_types=1);

namespace Identidad\Domain\Desafios;

use Core\Results\Error;

final class DesafioErrors
{
    /**
     * Un solo código para todas las causas —expirado, consumido, agotado,
     * equivocado, inexistente— a propósito: distinguirlas convierte al
     * endpoint en un oráculo.
     */
    public static function codigoInvalido(): Error
    {
        return Error::validation('CODIGO_INVALIDO', 'El código no es válido o ya venció');
    }

    public static function digitosInvalidos(): Error
    {
        return Error::validation('DIGITOS_INVALIDOS', 'El desafío debe ser de cuatro dígitos');
    }

    public static function limiteDeTasa(): Error
    {
        return Error::failure('LIMITE_DE_TASA', 'Demasiadas solicitudes para este número. Intente más tarde.');
    }
}
