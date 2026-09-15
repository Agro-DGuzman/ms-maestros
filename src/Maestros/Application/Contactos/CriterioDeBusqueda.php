<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

use InvalidArgumentException;

final readonly class CriterioDeBusqueda
{
    private const TAMANO_MAXIMO = 100;

    private function __construct(
        public ?string $texto,
        public FiltroDeEstado $estado,
        public int $pagina,
        public int $tamanoDePagina,
    ) {}

    public static function de(
        ?string $texto,
        FiltroDeEstado $estado,
        int $pagina = 1,
        int $tamanoDePagina = 25,
    ): self {
        if ($pagina < 1) {
            throw new InvalidArgumentException('PAGINA_INVALIDA');
        }

        // El tope existe para que nadie pida la tabla entera desde la barra de
        // direcciones cambiando un parámetro.
        if ($tamanoDePagina < 1 || $tamanoDePagina > self::TAMANO_MAXIMO) {
            throw new InvalidArgumentException('TAMANO_DE_PAGINA_INVALIDO');
        }

        $limpio = trim((string) $texto);

        return new self(
            texto: $limpio === '' ? null : $limpio,
            estado: $estado,
            pagina: $pagina,
            tamanoDePagina: $tamanoDePagina,
        );
    }

    public function salto(): int
    {
        return ($this->pagina - 1) * $this->tamanoDePagina;
    }
}
