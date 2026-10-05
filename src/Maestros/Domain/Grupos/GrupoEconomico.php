<?php

declare(strict_types=1);

namespace Maestros\Domain\Grupos;

use Core\Domain\AggregateRoot;
use DateTimeImmutable;

final class GrupoEconomico extends AggregateRoot
{
    private function __construct(
        IdDeGrupo $id,
        private readonly string $nombre,
        private readonly DateTimeImmutable $vigenteDesde,
        private readonly ?string $segmento,
    ) {
        parent::__construct($id);
    }

    /** El segmento es informativo (D11): no decide precios, crédito ni visibilidad. */
    public static function replica(IdDeGrupo $id, string $nombre, DateTimeImmutable $vigenteDesde, ?string $segmento = null): self
    {
        $segmento = $segmento === null ? null : trim($segmento);

        return new self($id, trim($nombre), $vigenteDesde, $segmento === '' ? null : $segmento);
    }

    public function segmento(): ?string
    {
        return $this->segmento;
    }

    public function idDeGrupo(): IdDeGrupo
    {
        $id = $this->id();
        assert($id instanceof IdDeGrupo);

        return $id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function debeReemplazarA(DateTimeImmutable $marca): bool
    {
        return $this->vigenteDesde > $marca;
    }
}
