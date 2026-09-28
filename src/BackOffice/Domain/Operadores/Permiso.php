<?php

declare(strict_types=1);

namespace BackOffice\Domain\Operadores;

/**
 * Qué sección del back-office puede usar un operador. Quien carga productos no
 * tiene por qué poder habilitar a un socio en la App, y al revés.
 */
enum Permiso: string
{
    case Accesos = 'accesos';
    case Catalogo = 'catalogo';

    /** @return list<self> */
    public static function todos(): array
    {
        return self::cases();
    }
}
