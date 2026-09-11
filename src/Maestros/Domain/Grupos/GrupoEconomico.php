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
    ) {
        parent::__construct($id);
    }

    public static function replica(IdDeGrupo $id, string $nombre, DateTimeImmutable $vigenteDesde): self
    {
        return new self($id, trim($nombre), $vigenteDesde);
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
