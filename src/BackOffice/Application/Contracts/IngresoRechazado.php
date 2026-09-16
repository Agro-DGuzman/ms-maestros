<?php

declare(strict_types=1);

namespace BackOffice\Application\Contracts;

use RuntimeException;

/**
 * El código distingue las causas porque la pantalla responde distinto a cada
 * una: corregir lo tecleado, reintentar, pedirle el rol a TI, o esperar. El
 * mensaje ya viene redactado para mostrarse tal cual.
 */
final class IngresoRechazado extends RuntimeException
{
    private function __construct(public readonly string $codigo, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public static function codigoInvalido(): self
    {
        return new self(
            'CODIGO_INVALIDO',
            'El ingreso no se pudo completar. Volvé a intentar desde la pantalla de entrada.',
        );
    }

    public static function credencialesInvalidas(): self
    {
        // Un solo mensaje para «ese correo no es de nadie» y «la contraseña no
        // es esa»: distinguirlos le confirma a quien prueba cuáles correos son
        // de operadores de verdad.
        return new self(
            'CREDENCIALES_INVALIDAS',
            'Correo o contraseña incorrectos.',
        );
    }

    public static function demasiadosIntentos(): self
    {
        return new self(
            'DEMASIADOS_INTENTOS',
            'Demasiados intentos fallidos. Esperá unos minutos y volvé a probar.',
        );
    }

    public static function sinRolAdministrador(): self
    {
        return new self(
            'SIN_ROL_ADMINISTRADOR',
            'Tu cuenta no tiene el rol Administrador del back-office. Pedíselo a TI.',
        );
    }

    public static function identidadNoDisponible(): self
    {
        return new self(
            'IDENTIDAD_NO_DISPONIBLE',
            'No pudimos validar tu identidad en este momento. Intentá de nuevo en unos minutos.',
        );
    }
}
