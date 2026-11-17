<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Identidad\Infrastructure\Persistence\EloquentSesionRepository;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Un logout que confirma justo después de que alguien leyó las sesiones
 * abiertas de la persona: lo que se leyó ya está cerrado en la base.
 */
final class SesionesQueSeCierranAlLeerlas implements SesionRepository
{
    private readonly EloquentSesionRepository $base;

    public function __construct()
    {
        $this->base = new EloquentSesionRepository;
    }

    public function abiertasDe(IdDePersona $persona): array
    {
        $leidas = $this->base->abiertasDe($persona);

        foreach ($leidas as $sesion) {
            $cerrada = $this->base->find($sesion->idDeSesion());
            $cerrada?->cerrar(new DateTimeImmutable);
            $cerrada === null ?: $this->base->save($cerrada);
        }

        return $leidas;
    }

    public function find(EntityId $id): ?SesionDeAplicacion
    {
        return $this->base->find($id);
    }

    public function add(AggregateRoot $agregado): void
    {
        $this->base->add($agregado);
    }

    public function save(AggregateRoot $agregado): void
    {
        $this->base->save($agregado);
    }

    public function porRefreshHash(string $hash): ?SesionDeAplicacion
    {
        return $this->base->porRefreshHash($hash);
    }

    public function abiertasConInstalacion(IdDeInstalacion $instalacion, DateTimeImmutable $ahora): array
    {
        return $this->base->abiertasConInstalacion($instalacion, $ahora);
    }

    public function abiertasConTokenPush(DateTimeImmutable $ahora): array
    {
        return $this->base->abiertasConTokenPush($ahora);
    }

    public function abiertasConEsteTokenPush(TokenPush $token, DateTimeImmutable $ahora): array
    {
        return $this->base->abiertasConEsteTokenPush($token, $ahora);
    }

    public function guardarTokenPushSiSigueAbierta(SesionDeAplicacion $sesion, DateTimeImmutable $ahora): bool
    {
        return $this->base->guardarTokenPushSiSigueAbierta($sesion, $ahora);
    }

    public function quitarEsteTokenPushDeOtras(TokenPush $token, IdDeSesion $excepto): void
    {
        $this->base->quitarEsteTokenPushDeOtras($token, $excepto);
    }

    public function quitarTokenPushSiSigueSiendo(IdDeSesion $sesion, TokenPush $token): void
    {
        $this->base->quitarTokenPushSiSigueSiendo($sesion, $token);
    }
}
