<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ListarContactos;

final readonly class PaginaDeAccesos
{
    /** @param list<ContactoConAcceso> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $pagina,
        public int $totalDePaginas,
    ) {}
}
