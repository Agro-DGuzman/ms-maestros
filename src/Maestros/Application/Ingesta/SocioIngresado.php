<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use DateTimeImmutable;

/**
 * Un socio con todas sus personas de contacto, como lo leyó el Sincronizador en
 * SAP. La forma ya viene validada por la capa HTTP.
 */
final readonly class SocioIngresado
{
    /** @param list<ContactoIngresado> $contactos */
    public function __construct(
        public string $cardCode,
        public string $razonSocial,
        public string $tipoSap,
        public bool $activo,
        public ?GrupoIngresado $grupo,
        public array $contactos,
        public DateTimeImmutable $vigenteDesde,
        public string $origenEsquema,
        public int $origenEventoId,
    ) {}
}
