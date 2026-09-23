<?php

declare(strict_types=1);

namespace Maestros\Domain\Aplicacion;

/** Lo que la App muestra al pedir que se actualice, escrito desde el servidor. */
final readonly class AvisoDeVersion
{
    public function __construct(
        public string $titulo,
        public string $mensaje,
    ) {}
}
