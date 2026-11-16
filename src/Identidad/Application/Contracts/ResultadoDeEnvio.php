<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

/** Qué hacer después de un envío, según lo que respondió el proveedor (NP20). */
final readonly class ResultadoDeEnvio
{
    private function __construct(
        public EstadoDeEnvio $estado,
        public ?int $esperaSegundos = null,
        public ?string $motivo = null,
    ) {}

    public static function entregado(): self
    {
        return new self(EstadoDeEnvio::Entregado);
    }

    public static function tokenMuerto(): self
    {
        return new self(EstadoDeEnvio::TokenMuerto);
    }

    public static function rechazado(string $motivo): self
    {
        return new self(EstadoDeEnvio::Rechazado, motivo: $motivo);
    }

    /** Sin espera, la decide el trabajo, que la hace crecer con cada intento. */
    public static function reintentarEn(?int $segundos): self
    {
        return new self(EstadoDeEnvio::Reintentar, $segundos);
    }
}
