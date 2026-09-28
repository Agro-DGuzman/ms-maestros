<?php

declare(strict_types=1);

namespace BackOffice\Domain\Catalogo;

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

/**
 * Un enlace del catálogo que alguien cambió. No se modifica ni se borra.
 *
 * El campo va como texto (el nombre de la columna) y no como el enum de
 * Maestros: el dominio del back-office no depende de otra capa de aplicación.
 * El nombre del operador y el código del producto se copian: el asiento tiene
 * que seguir diciendo quién y qué aunque después cambien.
 */
final readonly class CambioDeCatalogo
{
    private function __construct(
        public string $idDeCambio,
        public IdDeOperador $idDeOperador,
        public string $operador,
        public int $idProducto,
        public ?string $itemCode,
        public string $campo,
        public ?string $anterior,
        public ?string $nuevo,
        public DateTimeImmutable $ocurrioEl,
        public string $direccionIp,
    ) {}

    public static function nuevo(
        Operador $operador,
        int $idProducto,
        ?string $itemCode,
        string $campo,
        ?string $anterior,
        ?string $nuevo,
        string $direccionIp,
        DateTimeImmutable $ocurrioEl,
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $operador->id,
            $operador->nombre,
            $idProducto,
            $itemCode,
            $campo,
            $anterior,
            $nuevo,
            $ocurrioEl,
            $direccionIp,
        );
    }

    /** Rehidratación desde la base. No usar en código de aplicación. */
    public static function reconstituir(
        string $idDeCambio,
        IdDeOperador $idDeOperador,
        string $operador,
        int $idProducto,
        ?string $itemCode,
        string $campo,
        ?string $anterior,
        ?string $nuevo,
        DateTimeImmutable $ocurrioEl,
        string $direccionIp,
    ): self {
        return new self($idDeCambio, $idDeOperador, $operador, $idProducto, $itemCode, $campo, $anterior, $nuevo, $ocurrioEl, $direccionIp);
    }
}
