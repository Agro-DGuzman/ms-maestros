<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/** El grupo económico tal como lo manda el Sincronizador (`@GRUPOECO_SEGMENT`). */
final readonly class GrupoIngresado
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $segmento,
    ) {}
}
