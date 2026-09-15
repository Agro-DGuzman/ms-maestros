<?php

declare(strict_types=1);

namespace BackOffice\Application\Contracts;

use RuntimeException;

/**
 * El código distingue las tres causas porque la pantalla responde distinto a
 * cada una: reintentar, pedirle el rol a TI, o esperar. El mensaje ya viene
 * redactado para mostrarse tal cual.
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
