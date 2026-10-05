<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
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
        // Dos alcanzan para saber que no es una sola.
        $encontradas = $this->visibles()->where('c.celular', $celular->e164())->limit(2)->get();

        return $encontradas->count() === 1 ? $this->aDominio($encontradas->firstOrFail()) : null;
    }

    public function visible(IdDePersona $id): ?PersonaDeContacto
    {
        $record = $this->visibles()->where('c.id_de_persona', $id->value())->first();

        return $record === null ? null : $this->aDominio($record);
    }

    /**
     * La única definición de «visible» para un contacto: activo, sin baja, y
     * de un socio activo y sin baja. De acá cuelgan el alcance, el contexto,
     * el ingreso y la renovación de sesión.
     *
     * @return Builder<ContactoRecord>
     */
    private function visibles(): Builder
    {
        $socios = (new SocioRecord)->getTable();

        return ContactoRecord::query()
            ->from((new ContactoRecord)->getTable().' as c')
            ->join($socios.' as s', 's.codigo_de_socio', '=', 'c.codigo_de_socio')
            ->select('c.*')
            ->where('c.activo', true)
            ->whereNull('c.dado_de_baja_el')
            ->where('s.activo', true)
            ->whereNull('s.dado_de_baja_el');
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

    /** @param list<IdDePersona> $vistos */
    public function darDeBajaLosQueNoVinieron(CodigoDeSocio $socio, array $vistos, DateTimeImmutable $momento): void
    {
        $this->sinBajaFueraDe($vistos)
            ->where('codigo_de_socio', $socio->value())
            ->update(['dado_de_baja_el' => $momento->format('Y-m-d H:i:s')]);
    }

    /** @param list<IdDePersona> $vistos */
    public function darDeBajaAusentes(array $vistos, DateTimeImmutable $momento): void
    {
        $this->sinBajaFueraDe($vistos)
            ->update(['dado_de_baja_el' => $momento->format('Y-m-d H:i:s')]);
    }

    /**
     * Una baja anterior conserva su fecha: es cuándo dejó de estar en SAP.
     *
     * @param  list<IdDePersona>  $vistos
     * @return Builder<ContactoRecord>
     */
    private function sinBajaFueraDe(array $vistos): Builder
    {
        $consulta = ContactoRecord::query()->whereNull('dado_de_baja_el');

        if ($vistos !== []) {
            $consulta->whereNotIn('id_de_persona', array_map(
                static fn (IdDePersona $p): string => $p->value(),
                $vistos,
            ));
        }

        return $consulta;
    }

    private function aDominio(ContactoRecord $record): PersonaDeContacto
    {
        return PersonaDeContacto::replica(
            IdDePersona::desde((string) $record->id_de_persona),
            CodigoDeSocio::desde((string) $record->codigo_de_socio),
            (string) $record->nombre,
            is_string($record->celular) && $record->celular !== '' ? Celular::desdeLocalBoliviano($record->celular) : null,
            $record->habilitada_el?->toDateTimeImmutable(),
            $record->vigente_desde->toDateTimeImmutable(),
            activa: (bool) $record->activo,
            dadoDeBajaEl: $record->dado_de_baja_el?->toDateTimeImmutable(),
        );
    }

    /** @return array<string, string|bool|null> */
    private function aFila(PersonaDeContacto $persona): array
    {
        return [
            'id_de_persona' => $persona->idDePersona()->value(),
            'codigo_de_socio' => $persona->codigoDeSocio()->value(),
            'nombre' => $persona->nombre(),
            'celular' => $persona->celular()?->e164(),
            'habilitada_el' => $persona->habilitadaEl()?->format('Y-m-d H:i:s'),
            'vigente_desde' => $persona->vigenteDesde()->format('Y-m-d H:i:s'),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
            'activo' => $persona->activa(),
            'dado_de_baja_el' => $persona->dadoDeBajaEl()?->format('Y-m-d H:i:s'),
        ];
    }
}
