<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\FechaEnUtc;
use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Maestros\Domain\Contactos\AlcanceDeNumero;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;

final class EloquentContactoRepository implements ContactoRepository
{
    use FechaEnUtc;

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

    public function replicar(PersonaDeContacto $persona): void
    {
        $fila = $this->aFila($persona);
        unset($fila['habilitada_el']);

        ContactoRecord::query()->updateOrCreate(['id_de_persona' => $persona->idDePersona()->value()], $fila);
    }

    public function alcanceDeNumero(Celular $celular): AlcanceDeNumero
    {
        return $this->alcancesDeNumeros([$celular])[$celular->e164()];
    }

    /**
     * @param  list<Celular>  $celulares
     * @return array<string, AlcanceDeNumero>
     */
    public function alcancesDeNumeros(array $celulares): array
    {
        $numeros = array_values(array_unique(array_map(static fn (Celular $c): string => $c->e164(), $celulares)));

        if ($numeros === []) {
            return [];
        }

        $filas = $this->visibles()
            ->whereIn('c.celular', $numeros)
            ->select(['c.celular', 's.codigo_de_socio', 's.id_de_grupo'])
            ->distinct()
            ->orderBy('c.celular')
            ->orderBy('s.codigo_de_socio')
            ->toBase()
            ->get();

        $vinculos = array_fill_keys($numeros, []);

        foreach ($filas as $fila) {
            $vinculos[(string) $fila->celular][] = [
                'socio' => CodigoDeSocio::desde((string) $fila->codigo_de_socio),
                'grupo' => is_string($fila->id_de_grupo) && $fila->id_de_grupo !== '' ? IdDeGrupo::desde($fila->id_de_grupo) : null,
            ];
        }

        // Un número sin contactos visibles también tiene alcance: el que no entra.
        return array_map(static fn (array $v): AlcanceDeNumero => AlcanceDeNumero::desde($v), $vinculos);
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

        $consulta = ContactoRecord::query()
            ->from((new ContactoRecord)->getTable().' as c')
            ->join($socios.' as s', 's.codigo_de_socio', '=', 'c.codigo_de_socio')
            ->select('c.*');

        Visibilidad::exigir($consulta, 'c', 's');

        return $consulta;
    }

    /** @param list<IdDePersona> $vistos */
    public function darDeBajaLosQueNoVinieron(CodigoDeSocio $socio, array $vistos, DateTimeImmutable $momento): void
    {
        $this->sinBajaFueraDe($vistos)
            ->where('codigo_de_socio', $socio->value())
            ->update(['dado_de_baja_el' => self::enUtc($momento)]);
    }

    /** @param list<IdDePersona> $vistos */
    public function darDeBajaAusentes(array $vistos, DateTimeImmutable $momento): void
    {
        $this->sinBajaFueraDe($vistos)
            ->update(['dado_de_baja_el' => self::enUtc($momento)]);
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
            'habilitada_el' => self::enUtc($persona->habilitadaEl()),
            'vigente_desde' => self::enUtc($persona->vigenteDesde()),
            'importado_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
            'activo' => $persona->activa(),
            'dado_de_baja_el' => self::enUtc($persona->dadoDeBajaEl()),
        ];
    }
}
