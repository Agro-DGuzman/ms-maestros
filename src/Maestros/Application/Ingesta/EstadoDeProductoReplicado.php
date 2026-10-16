<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use DateTimeImmutable;

/**
 * Lo único que el caso de uso necesita saber de un producto guardado para
 * decidir: de cuándo es su ficha y si está dado de baja. Un producto de la web
 * no tiene vigencia, y entonces acepta cualquier cuerpo.
 */
final readonly class EstadoDeProductoReplicado
{
    public function __construct(
        public ?DateTimeImmutable $vigenteDesde,
        public bool $dadoDeBaja,
    ) {}
}
