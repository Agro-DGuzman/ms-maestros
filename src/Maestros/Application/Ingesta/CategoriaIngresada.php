<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/**
 * La categoría del artículo, por código (P4). El código se recorta y se pasa a
 * minúsculas, como los seis que ya existen: SQL Server compara sin distinguir
 * mayúsculas y SQLite sí, así un «INSECTICIDAS» cae en la misma categoría en
 * los dos motores.
 */
final readonly class CategoriaIngresada
{
    private function __construct(
        public string $codigo,
        public string $nombre,
    ) {}

    public static function de(string $codigo, string $nombre): self
    {
        return new self(mb_strtolower(trim($codigo)), trim($nombre));
    }
}
