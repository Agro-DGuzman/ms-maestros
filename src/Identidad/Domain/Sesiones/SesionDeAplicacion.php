<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\Dispositivo;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * El período durante el cual una persona opera la App desde un dispositivo.
 * Nunca se edita —se abre y se cierra— pero es entidad y no objeto de valor
 * porque importa *cuál*: se audita, se revoca, se correlaciona.
 */
final class SesionDeAplicacion extends AggregateRoot
{
    private function __construct(
        IdDeSesion $id,
        private readonly IdDePersona $persona,
        private readonly ?Dispositivo $dispositivo,
        private readonly DateTimeImmutable $iniciadaEn,
        private readonly DateTimeImmutable $expiraEn,
        private ?DateTimeImmutable $cerradaEn,
        private ?string $refreshHash = null,
    ) {
        parent::__construct($id);
    }

    public static function abrir(
        IdDeSesion $id,
        IdDePersona $persona,
        ?Dispositivo $dispositivo,
        DateTimeImmutable $iniciadaEn,
        DateTimeImmutable $expiraEn,
    ): self {
        return new self($id, $persona, $dispositivo, $iniciadaEn, $expiraEn, null);
    }

    public static function reconstituir(
        IdDeSesion $id,
        IdDePersona $persona,
        ?Dispositivo $dispositivo,
        DateTimeImmutable $iniciadaEn,
        DateTimeImmutable $expiraEn,
        ?DateTimeImmutable $cerradaEn,
        ?string $refreshHash = null,
    ): self {
        return new self($id, $persona, $dispositivo, $iniciadaEn, $expiraEn, $cerradaEn, $refreshHash);
    }

    public function cerrar(DateTimeImmutable $ahora): void
    {
        $this->cerradaEn ??= $ahora;
    }

    /** Solo el hash: el refresh token en claro nunca se guarda. */
    public function asociarRefresh(string $refreshToken): void
    {
        $this->refreshHash = hash('sha256', $refreshToken);
    }

    public function refreshHash(): ?string
    {
        return $this->refreshHash;
    }

    public function estaAbierta(DateTimeImmutable $ahora): bool
    {
        return $this->cerradaEn === null && $ahora <= $this->expiraEn;
    }

    public function idDeSesion(): IdDeSesion
    {
        $id = $this->id();
        assert($id instanceof IdDeSesion);

        return $id;
    }

    public function persona(): IdDePersona
    {
        return $this->persona;
    }

    public function dispositivo(): ?Dispositivo
    {
        return $this->dispositivo;
    }

    public function iniciadaEn(): DateTimeImmutable
    {
        return $this->iniciadaEn;
    }

    public function expiraEn(): DateTimeImmutable
    {
        return $this->expiraEn;
    }

    public function cerradaEn(): ?DateTimeImmutable
    {
        return $this->cerradaEn;
    }
}
