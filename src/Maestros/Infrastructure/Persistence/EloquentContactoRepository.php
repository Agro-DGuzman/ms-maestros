<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Socios\CodigoDeSocio;

final class EloquentContactoRepository implements ContactoRepository
{
    public function find(EntityId $id): ?PersonaDeContacto
    {
        $record = ContactoRecord::query()->find($id->value());

        return $record === null ? null : $this->aDominio($record);
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof PersonaDeContacto);
        ContactoRecord::query()->create($this->aFila($agregado));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof PersonaDeContacto);
        ContactoRecord::query()->updateOrCreate(
            ['id_de_persona' => $agregado->idDePersona()->value()],
            $this->aFila($agregado),
        );
    }

    public function porCelular(Celular $celular): ?PersonaDeContacto
    {
        $record = ContactoRecord::query()->where('celular', $celular->e164())->first();

        return $record === null ? null : $this->aDominio($record);
    }

    /** @return list<PersonaDeContacto> */
    public function habilitadas(): array
    {
        return array_values(
            ContactoRecord::query()
                ->whereNotNull('habilitada_el')
                ->orderBy('id_de_persona')
                ->get()
                ->map(fn (ContactoRecord $r): PersonaDeContacto => $this->aDominio($r))
                ->all(),
        );
    }

    /** @param list<IdDePersona> $personas */
    public function marcarVistasEnImportacion(array $personas, DateTimeImmutable $momento): void
    {
        if ($personas === []) {
            return;
        }

        ContactoRecord::query()
            ->whereIn('id_de_persona', array_map(
                static fn (IdDePersona $p): string => $p->value(),
                $personas,
            ))
            ->update(['vista_en_importacion_el' => $momento->format('Y-m-d H:i:s')]);
    }

    private function aDominio(ContactoRecord $record): PersonaDeContacto
    {
        return PersonaDeContacto::replica(
            IdDePersona::desde((string) $record->id_de_persona),
            CodigoDeSocio::desde((string) $record->codigo_de_socio),
            (string) $record->nombre,
            Celular::desdeLocalBoliviano((string) $record->celular),
            $record->habilitada_el?->toDateTimeImmutable(),
            $record->vigente_desde->toDateTimeImmutable(),
        );
    }

    /** @return array<string, string|null> */
    private function aFila(PersonaDeContacto $persona): array
    {
        return [
            'id_de_persona' => $persona->idDePersona()->value(),
            'codigo_de_socio' => $persona->codigoDeSocio()->value(),
            'nombre' => $persona->nombre(),
            'celular' => $persona->celular()->e164(),
            'habilitada_el' => $persona->habilitadaEl()?->format('Y-m-d H:i:s'),
            'vigente_desde' => $persona->vigenteDesde()->format('Y-m-d H:i:s'),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ];
    }
}
