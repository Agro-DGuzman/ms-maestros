<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Maestros\Domain\Contactos\IdDePersona;

final class EloquentSesionRepository implements SesionRepository
{
    public function find(EntityId $id): ?SesionDeAplicacion
    {
        $record = SesionRecord::query()->find($id->value());

        return $record === null ? null : $this->aDominio($record);
    }

    public function add(AggregateRoot $agregado): void
    {
        assert($agregado instanceof SesionDeAplicacion);
        self::sinValoresEnElError(fn () => SesionRecord::query()->create($this->aFila($agregado)));
    }

    public function save(AggregateRoot $agregado): void
    {
        assert($agregado instanceof SesionDeAplicacion);
        self::sinValoresEnElError(fn () => SesionRecord::query()->updateOrCreate(
            ['id_de_sesion' => $agregado->idDeSesion()->value()],
            $this->aFila($agregado),
        ));
    }

    /**
     * Laravel arma el mensaje de una QueryException con los valores de la
     * consulta, y ese mensaje va al log: entre ellos está el token push, que no
     * se escribe nunca (NP16). Se relanza la misma excepción con los valores
     * tapados; el SQL y el error del motor siguen ahí. Va en toda consulta que
     * lleva el token, también en las que solo leen.
     *
     * @template T
     *
     * @param  callable(): T  $consulta
     * @return T
     */
    private static function sinValoresEnElError(callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (QueryException $e) {
            throw new QueryException(
                $e->getConnectionName(),
                $e->getSql(),
                $e->getBindings(),
                $e->getPrevious() ?? $e,
                maskBindings: true,
            );
        }
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

    /** @return list<SesionDeAplicacion> */
    public function abiertasConInstalacion(IdDeInstalacion $instalacion, DateTimeImmutable $ahora): array
    {
        return $this->abiertas(
            SesionRecord::query()->where('id_de_instalacion', $instalacion->value()),
            $ahora,
        );
    }

    /** @return list<SesionDeAplicacion> */
    public function abiertasConTokenPush(DateTimeImmutable $ahora): array
    {
        return $this->abiertas(SesionRecord::query()->whereNotNull('token_push'), $ahora);
    }

    /** @return list<SesionDeAplicacion> */
    public function abiertasConEsteTokenPush(TokenPush $token, DateTimeImmutable $ahora): array
    {
        return self::sinValoresEnElError(
            fn (): array => $this->abiertas(SesionRecord::query()->where('token_push', $token->valor()), $ahora),
        );
    }

    public function guardarTokenPushSiSigueAbierta(SesionDeAplicacion $sesion, DateTimeImmutable $ahora): bool
    {
        $instalacion = $sesion->dispositivo()?->instalacion()->value();

        if ($instalacion === null) {
            return false;
        }

        $cambiadas = self::sinValoresEnElError(fn (): int => SesionRecord::query()
            ->where('id_de_sesion', $sesion->idDeSesion()->value())
            ->where('id_de_instalacion', $instalacion)
            ->whereNull('cerrada_en')
            ->where('expira_en', '>=', $ahora->format('Y-m-d H:i:s'))
            ->update([
                'token_push' => $sesion->tokenPush()?->valor(),
                'token_push_actualizado_en' => $sesion->tokenPushActualizadoEn()?->format('Y-m-d H:i:s'),
            ]));

        return $cambiadas > 0;
    }

    public function quitarEsteTokenPushDeOtras(TokenPush $token, IdDeSesion $excepto): void
    {
        self::sinValoresEnElError(fn (): int => SesionRecord::query()
            ->where('token_push', $token->valor())
            ->where('id_de_sesion', '<>', $excepto->value())
            ->update(['token_push' => null, 'token_push_actualizado_en' => null]));
    }

    public function quitarTokenPushSiSigueSiendo(IdDeSesion $sesion, TokenPush $token): void
    {
        self::sinValoresEnElError(fn (): int => SesionRecord::query()
            ->where('id_de_sesion', $sesion->value())
            ->where('token_push', $token->valor())
            ->update(['token_push' => null, 'token_push_actualizado_en' => null]));
    }

    /**
     * @param  Builder<SesionRecord>  $consulta
     * @return list<SesionDeAplicacion>
     */
    private function abiertas(Builder $consulta, DateTimeImmutable $ahora): array
    {
        return array_values(
            $consulta
                ->whereNull('cerrada_en')
                ->where('expira_en', '>=', $ahora->format('Y-m-d H:i:s'))
                ->orderBy('id_de_sesion')
                ->get()
                ->map(fn (SesionRecord $r): SesionDeAplicacion => $this->aDominio($r))
                ->all(),
        );
    }

    private function aDominio(SesionRecord $record): SesionDeAplicacion
    {
        $instalacion = $record->id_de_instalacion;
        $plataforma = $record->plataforma;
        $token = $record->token_push;

        $dispositivo = $instalacion === null || $plataforma === null
            ? null
            : Dispositivo::registrar(
                IdDeInstalacion::desde((string) $instalacion),
                Plataforma::from((string) $plataforma),
                $token === null ? null : TokenPush::desde((string) $token),
            );

        return SesionDeAplicacion::reconstituir(
            IdDeSesion::desde((string) $record->id_de_sesion),
            IdDePersona::desde((string) $record->id_de_persona),
            $dispositivo,
            $record->iniciada_en->toDateTimeImmutable(),
            $record->expira_en->toDateTimeImmutable(),
            $record->cerrada_en?->toDateTimeImmutable(),
            $record->refresh_token_hash === null ? null : (string) $record->refresh_token_hash,
            $record->token_push_actualizado_en?->toDateTimeImmutable(),
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
            'token_push' => $sesion->tokenPush()?->valor(),
            'token_push_actualizado_en' => $sesion->tokenPushActualizadoEn()?->format('Y-m-d H:i:s'),
            'refresh_token_hash' => $sesion->refreshHash(),
            'iniciada_en' => $sesion->iniciadaEn()->format('Y-m-d H:i:s'),
            'expira_en' => $sesion->expiraEn()->format('Y-m-d H:i:s'),
            'cerrada_en' => $sesion->cerradaEn()?->format('Y-m-d H:i:s'),
        ];
    }
}
