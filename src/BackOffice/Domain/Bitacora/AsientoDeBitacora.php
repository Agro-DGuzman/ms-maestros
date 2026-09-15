<?php

declare(strict_types=1);

namespace BackOffice\Domain\Bitacora;

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Un hecho ocurrido. No se modifica ni se borra.
 *
 * El nombre del operador se copia en vez de resolverse después: si TI da de
 * baja a esa persona en Entra, el asiento tiene que seguir diciendo quién fue.
 */
final readonly class AsientoDeBitacora
{
    private function __construct(
        public string $idDeAsiento,
        public IdDeOperador $idDeOperador,
        public string $operador,
        public string $idDePersona,
        public AccionDeAcceso $accion,
        public DateTimeImmutable $ocurrioEl,
        public string $direccionIp,
    ) {}

    public static function nuevo(
        Operador $operador,
        string $idDePersona,
        AccionDeAcceso $accion,
        string $direccionIp,
        DateTimeImmutable $ocurrioEl,
    ): self {
        return new self(
            idDeAsiento: Uuid::uuid4()->toString(),
            idDeOperador: $operador->id,
            operador: $operador->nombre,
            idDePersona: $idDePersona,
            accion: $accion,
            ocurrioEl: $ocurrioEl,
            direccionIp: $direccionIp,
        );
    }

    /** Rehidratación desde la base. No usar en código de aplicación. */
    public static function reconstituir(
        string $idDeAsiento,
        IdDeOperador $idDeOperador,
        string $operador,
        string $idDePersona,
        AccionDeAcceso $accion,
        DateTimeImmutable $ocurrioEl,
        string $direccionIp,
    ): self {
        return new self(
            $idDeAsiento,
            $idDeOperador,
            $operador,
            $idDePersona,
            $accion,
            $ocurrioEl,
            $direccionIp,
        );
    }
}
