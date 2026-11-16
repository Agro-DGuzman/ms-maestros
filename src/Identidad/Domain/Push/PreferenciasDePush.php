<?php

declare(strict_types=1);

namespace Identidad\Domain\Push;

use DateTimeImmutable;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Los cuatro interruptores de «Preferencias de avisos» de una persona,
 * copiados de ms-comercial por evento (NP8). Una categoría apagada corta solo
 * el push: el aviso sigue en la bandeja. Sin copia, todo está prendido, igual
 * que en ms-comercial.
 */
final readonly class PreferenciasDePush
{
    private function __construct(
        private IdDePersona $persona,
        private bool $facturas,
        private bool $pagos,
        private bool $reportes,
        private bool $catalogo,
        private ?DateTimeImmutable $vigenteDesde,
    ) {}

    public static function de(
        IdDePersona $persona,
        bool $facturas,
        bool $pagos,
        bool $reportes,
        bool $catalogo,
        DateTimeImmutable $vigenteDesde,
    ): self {
        return new self($persona, $facturas, $pagos, $reportes, $catalogo, $vigenteDesde);
    }

    public static function todasPrendidas(IdDePersona $persona): self
    {
        return new self($persona, true, true, true, true, null);
    }

    public function permite(CategoriaDeAviso $categoria): bool
    {
        return match ($categoria) {
            // El vencimiento es de una factura: lo gobierna el mismo interruptor.
            CategoriaDeAviso::Factura, CategoriaDeAviso::Vencimiento => $this->facturas,
            CategoriaDeAviso::Pago => $this->pagos,
            CategoriaDeAviso::ReporteCampo => $this->reportes,
            CategoriaDeAviso::Catalogo => $this->catalogo,
        };
    }

    public function persona(): IdDePersona
    {
        return $this->persona;
    }

    public function facturas(): bool
    {
        return $this->facturas;
    }

    public function pagos(): bool
    {
        return $this->pagos;
    }

    public function reportes(): bool
    {
        return $this->reportes;
    }

    public function catalogo(): bool
    {
        return $this->catalogo;
    }

    /** Null en las de todasPrendidas, que nunca llegaron por un evento. */
    public function vigenteDesde(): ?DateTimeImmutable
    {
        return $this->vigenteDesde;
    }
}
