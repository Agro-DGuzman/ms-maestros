<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Keycloak;

use Core\Results\Error;

final class KeycloakErrors
{
    public static function noDisponible(string $detalle): Error
    {
        return Error::problem(
            'IDENTIDAD_NO_DISPONIBLE',
            'El proveedor de identidad no respondió: {detalle}',
            $detalle,
        );
    }

    public static function credencialRechazada(): Error
    {
        return Error::problem(
            'IDENTIDAD_NO_DISPONIBLE',
            'El proveedor de identidad rechazó la credencial del servicio',
        );
    }
}
