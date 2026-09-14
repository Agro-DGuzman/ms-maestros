<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;

final class EloquentGrupoRepository implements GrupoRepository
{
    public function find(EntityId $id): ?GrupoEconomico
    {
        $record = GrupoRecord::query()->find($id->value());

        return $record === null ? null : $this->aDominio($record);
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof GrupoEconomico);
        GrupoRecord::query()->create($this->aFila($agregado));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof GrupoEconomico);
        GrupoRecord::query()->updateOrCreate(
            ['id_de_grupo' => $agregado->idDeGrupo()->value()],
            $this->aFila($agregado),
        );
    }

    private function aDominio(GrupoRecord $record): GrupoEconomico
    {
        return GrupoEconomico::replica(
            IdDeGrupo::desde((string) $record->id_de_grupo),
            (string) $record->nombre,
            $record->vigente_desde->toDateTimeImmutable(),
        );
    }

    /** @return array<string, string> */
    private function aFila(GrupoEconomico $grupo): array
    {
        return [
            'id_de_grupo' => $grupo->idDeGrupo()->value(),
            'nombre' => $grupo->nombre(),
            'vigente_desde' => $grupo->vigenteDesde()->format('Y-m-d H:i:s'),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ];
    }
}
