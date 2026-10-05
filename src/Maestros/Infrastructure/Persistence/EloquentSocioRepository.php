<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\FechaEnUtc;
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
    use FechaEnUtc;

    public function find(EntityId $id): ?Socio
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
                // Los mismos dos que Socio::esVisible(): lo inactivo o dado
                // de baja no aparece en el selector ni cuenta para el grupo.
                ->where('activo', true)
                ->whereNull('dado_de_baja_el')
                ->orderBy('codigo_de_socio')
                ->get()
                ->map(fn (SocioRecord $r): Socio => $this->aDominio($r))
                ->all(),
        );
    }

    private function aDominio(SocioRecord $record): Socio
    {
        $grupo = $record->id_de_grupo;

        return Socio::replica(
            CodigoDeSocio::desde((string) $record->codigo_de_socio),
            RazonSocial::desde((string) $record->razon_social),
            is_string($grupo) && trim($grupo) !== '' ? IdDeGrupo::desde($grupo) : null,
            $record->vigente_desde->toDateTimeImmutable(),
            activo: (bool) $record->activo,
            dadoDeBajaEl: $record->dado_de_baja_el?->toDateTimeImmutable(),
            origenEsquema: $record->origen_esquema,
            origenEventoId: $record->origen_evento_id,
        );
    }

    /** @return array<string, string|int|bool|null> */
    private function aFila(Socio $socio): array
    {
        return [
            'codigo_de_socio' => $socio->codigoDeSocio()->value(),
            'razon_social' => $socio->razonSocial()->texto(),
            'id_de_grupo' => $socio->grupo()?->value(),
            'vigente_desde' => self::enUtc($socio->vigenteDesde()),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
            'activo' => $socio->activo(),
            'dado_de_baja_el' => self::enUtc($socio->dadoDeBajaEl()),
            'origen_esquema' => $socio->origenEsquema(),
            'origen_evento_id' => $socio->origenEventoId(),
        ];
    }
}
