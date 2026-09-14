<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class EloquentDesafioRepository implements DesafioRepository
{
    public function find(EntityId $id): ?DesafioDeIngreso
    {
        $record = DesafioRecord::query()->find($id->value());

        if ($record === null) {
            return null;
        }

        return DesafioDeIngreso::reconstituir(
            IdDeDesafio::desde((string) $record->id_de_desafio),
            Celular::desdeLocalBoliviano((string) $record->celular),
            (string) $record->digitos,
            $record->expira_en->toDateTimeImmutable(),
            (int) $record->intentos_fallidos,
            (bool) $record->consumido,
        );
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof DesafioDeIngreso);
        DesafioRecord::query()->create($this->aFila($agregado, emitido: true));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof DesafioDeIngreso);
        DesafioRecord::query()->updateOrCreate(
            ['id_de_desafio' => $agregado->idDeDesafio()->value()],
            $this->aFila($agregado, emitido: false),
        );
    }

    public function emitidosDesde(Celular $celular, DateTimeImmutable $desde): int
    {
        return DesafioRecord::query()
            ->where('celular', $celular->e164())
            ->where('emitido_el', '>=', $desde->format('Y-m-d H:i:s'))
            ->count();
    }

    /** @return array<string, string|int|bool> */
    private function aFila(DesafioDeIngreso $desafio, bool $emitido): array
    {
        $fila = [
            'id_de_desafio' => $desafio->idDeDesafio()->value(),
            'celular' => $desafio->celular()->e164(),
            'digitos' => $desafio->digitos(),
            'expira_en' => $desafio->expiraEn()->format('Y-m-d H:i:s'),
            'intentos_fallidos' => $desafio->intentosFallidos(),
            'consumido' => $desafio->estaConsumido(),
        ];

        if ($emitido) {
            $fila['emitido_el'] = (new DateTimeImmutable)->format('Y-m-d H:i:s');
        }

        return $fila;
    }
}
