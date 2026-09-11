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
    public function find(EntityId $id, bool $readOnly = false): ?PersonaDeContacto
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

    private function aDominio(ContactoRecord $record): PersonaDeContacto
    {
        return PersonaDeContacto::replica(
            IdDePersona::desde((string) $record->id_de_persona),
            CodigoDeSocio::desde((string) $record->codigo_de_socio),
            (string) $record->nombre,
            Celular::desdeLocalBoliviano((string) $record->celular),
            $record->habilitada_el === null ? null : new DateTimeImmutable((string) $record->habilitada_el),
            new DateTimeImmutable((string) $record->vigente_desde),
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
            'importado_el' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
    }
}
