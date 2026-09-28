<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class CambioDeEnlace
{
    public function __construct(
        public CampoDeEnlace $campo,
        public ?string $anterior,
        public ?string $nuevo,
    ) {}
}
