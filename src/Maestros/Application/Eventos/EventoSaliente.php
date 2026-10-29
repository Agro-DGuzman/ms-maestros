<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use DateTimeImmutable;

/**
 * Un evento de dominio que ms-maestros publica, con el sobre entero tal como
 * va al bus (spec de los eventos de dominio, EM1). `creadoEn` ordena el outbox.
 */
final readonly class EventoSaliente
{
    /** @param array<string, mixed> $sobre */
    public function __construct(
        public string $eventoId,
        public string $tipo,
        public array $sobre,
        public DateTimeImmutable $creadoEn,
    ) {}
}
