<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

/** Una persona de contacto tal como la manda el Sincronizador (`OCPR`). */
final readonly class ContactoIngresado
{
    public function __construct(
        public string $codigo,
        public string $nombre,
        public ?string $celular,
        public bool $activo,
    ) {}
}
