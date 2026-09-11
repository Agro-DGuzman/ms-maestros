<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Maestros\Domain\Contactos\IdDePersona;

final class EloquentSesionRepository implements SesionRepository
{
    public function find(EntityId $id, bool $readOnly = false): ?SesionDeAplicacion
    {
        $record = SesionRecord::query()->find($id->value());

        return $record === null ? null : $this->aDominio($record);
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof SesionDeAplicacion);
        SesionRecord::query()->create($this->aFila($agregado));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof SesionDeAplicacion);
        SesionRecord::query()->updateOrCreate(
            ['id_de_sesion' => $agregado->idDeSesion()->value()],
            $this->aFila($agregado),
        );
    }

    /** @return list<SesionDeAplicacion> */
    public function abiertasDe(IdDePersona $persona): array
    {
        return array_values(
            SesionRecord::query()
                ->where('id_de_persona', $persona->value())
                ->whereNull('cerrada_en')
                ->where('expira_en', '>=', (new DateTimeImmutable)->format('Y-m-d H:i:s'))
                ->orderBy('id_de_sesion')
                ->get()
                ->map(fn (SesionRecord $r): SesionDeAplicacion => $this->aDominio($r))
                ->all(),
        );
    }

    public function porRefreshHash(string $hash): ?SesionDeAplicacion
    {
        $record = SesionRecord::query()->where('refresh_token_hash', $hash)->first();

        return $record === null ? null : $this->aDominio($record);
    }

    private function aDominio(SesionRecord $record): SesionDeAplicacion
    {
        $instalacion = $record->id_de_instalacion;
        $plataforma = $record->plataforma;

        $dispositivo = $instalacion === null || $plataforma === null
            ? null
            : Dispositivo::registrar(
                IdDeInstalacion::desde((string) $instalacion),
                Plataforma::from((string) $plataforma),
            );

        return SesionDeAplicacion::reconstituir(
            IdDeSesion::desde((string) $record->id_de_sesion),
            IdDePersona::desde((string) $record->id_de_persona),
            $dispositivo,
            $record->iniciada_en->toDateTimeImmutable(),
            $record->expira_en->toDateTimeImmutable(),
            $record->cerrada_en?->toDateTimeImmutable(),
            $record->refresh_token_hash === null ? null : (string) $record->refresh_token_hash,
        );
    }

    /** @return array<string, string|null> */
    private function aFila(SesionDeAplicacion $sesion): array
    {
        $dispositivo = $sesion->dispositivo();

        return [
            'id_de_sesion' => $sesion->idDeSesion()->value(),
            'id_de_persona' => $sesion->persona()->value(),
            'id_de_instalacion' => $dispositivo?->instalacion()->value(),
            'plataforma' => $dispositivo?->plataforma()->value,
            'refresh_token_hash' => $sesion->refreshHash(),
            'iniciada_en' => $sesion->iniciadaEn()->format('Y-m-d H:i:s'),
            'expira_en' => $sesion->expiraEn()->format('Y-m-d H:i:s'),
            'cerrada_en' => $sesion->cerradaEn()?->format('Y-m-d H:i:s'),
        ];
    }
}
