<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/** El registro del artículo: hoy SAP manda siempre SENASAG, y solo los AGR lo tienen. */
final readonly class RegistroIngresado
{
    public function __construct(
        public string $entidad,
        public string $numero,
    ) {}
}
