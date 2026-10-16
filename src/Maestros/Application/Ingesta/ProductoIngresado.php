<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use DateTimeImmutable;

/**
 * Un artículo de SAP tal como lo leyó el Sincronizador (P1: cada artículo es un
 * producto). La forma ya viene validada por la capa HTTP, y lo que no está acá
 * —la descripción y los cuatro enlaces— no lo escribe la ingesta.
 */
final readonly class ProductoIngresado
{
    /** @param list<string> $cultivos */
    public function __construct(
        public string $itemCode,
        public string $nombre,
        public bool $activo,
        public ?CategoriaIngresada $categoria,
        public ?string $presentacion,
        public ?string $ingredienteActivo,
        public ?string $formulacionCodigo,
        public ?string $formulacionDescripcion,
        public ?string $dosisReferencial,
        public array $cultivos,
        public ?RegistroIngresado $registro,
        public DateTimeImmutable $vigenteDesde,
        public string $origenEsquema,
        public int $origenEventoId,
    ) {}

    /** El mismo formato que ya usa el catálogo: «SUSPENSION CONCENTRADA (SC)». */
    public function formulacion(): ?string
    {
        return match (true) {
            $this->formulacionDescripcion !== null && $this->formulacionCodigo !== null => "{$this->formulacionDescripcion} ({$this->formulacionCodigo})",
            $this->formulacionDescripcion !== null => $this->formulacionDescripcion,
            default => $this->formulacionCodigo,
        };
    }

    /**
     * La clave primaria de `producto_cultivo` en SQL Server no distingue
     * mayúsculas: dos cultivos que solo difieren en eso harían fallar el
     * envío entero. Gana la primera escritura, y los acentos sí distinguen.
     *
     * @return list<string>
     */
    public function cultivosSinRepetir(): array
    {
        $vistos = [];

        foreach ($this->cultivos as $cultivo) {
            $vistos[mb_strtolower($cultivo)] ??= $cultivo;
        }

        return array_values($vistos);
    }
}
