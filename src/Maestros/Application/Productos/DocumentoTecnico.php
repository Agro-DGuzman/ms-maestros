<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class DocumentoTecnico
{
    public function __construct(
        public TipoDeDocumento $tipo,
        public string $url,
    ) {}
}
