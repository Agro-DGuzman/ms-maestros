<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use LogicException;
use Maestros\Domain\Contactos\IdDePersona;

final class PersonaAutenticada
{
    public const ATRIBUTO = 'persona_autenticada';

    public static function deLaPeticion(Request $peticion): IdDePersona
    {
        $persona = $peticion->attributes->get(self::ATRIBUTO);

        if (! $persona instanceof IdDePersona) {
            throw new LogicException('La ruta no pasó por el middleware auth.token');
        }

        return $persona;
    }
}
