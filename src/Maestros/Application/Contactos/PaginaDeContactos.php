<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

final readonly class PaginaDeContactos
{
    /** @param list<ContactoDeBackOffice> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $pagina,
        public int $tamanoDePagina,
    ) {}

    public function totalDePaginas(): int
    {
        return max(1, (int) ceil($this->total / $this->tamanoDePagina));
    }
}
