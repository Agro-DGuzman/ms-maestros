<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

final class EloquentSocioRepository implements SocioRepository
{
    public function find(EntityId $id, bool $readOnly = false): ?Socio
    {
        $record = SocioRecord::query()->find($id->value());

        return $record === null ? null : $this->aDominio($record);
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof Socio);
        SocioRecord::query()->create($this->aFila($agregado));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof Socio);
        SocioRecord::query()->updateOrCreate(
            ['codigo_de_socio' => $agregado->codigoDeSocio()->value()],
            $this->aFila($agregado),
        );
    }

    /** @return list<Socio> */
    public function porGrupo(IdDeGrupo $grupo): array
    {
        return array_values(
            SocioRecord::query()
                ->where('id_de_grupo', $grupo->value())
                ->orderBy('codigo_de_socio')
                ->get()
                ->map(fn (SocioRecord $r): Socio => $this->aDominio($r))
                ->all(),
        );
    }

    private function aDominio(SocioRecord $record): Socio
    {
        return Socio::replica(
            CodigoDeSocio::desde((string) $record->codigo_de_socio),
            RazonSocial::desde((string) $record->razon_social),
            IdDeGrupo::desde((string) $record->id_de_grupo),
            $record->vigente_desde->toDateTimeImmutable(),
        );
    }

    /** @return array<string, string> */
    private function aFila(Socio $socio): array
    {
        return [
            'codigo_de_socio' => $socio->codigoDeSocio()->value(),
            'razon_social' => $socio->razonSocial()->texto(),
            'id_de_grupo' => $socio->idDeGrupo()->value(),
            'vigente_desde' => $socio->vigenteDesde()->format('Y-m-d H:i:s'),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ];
    }
}
