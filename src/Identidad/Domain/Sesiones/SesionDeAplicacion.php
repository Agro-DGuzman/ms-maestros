<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\TokenPush;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * El período durante el cual una persona opera la App desde un dispositivo.
 * Es entidad y no objeto de valor porque importa *cuál*: se audita, se
 * revoca, se correlaciona.
 *
 * El dispositivo puede perderse antes del cierre: un teléfono es de la última
 * sesión que lo registró (NP4), así que a otro que entra en el mismo teléfono
 * no le llegan los avisos de quien estaba antes.
 */
final class SesionDeAplicacion extends AggregateRoot
{
    private function __construct(
        IdDeSesion $id,
        private readonly IdDePersona $persona,
        private ?Dispositivo $dispositivo,
        private readonly DateTimeImmutable $iniciadaEn,
        private readonly DateTimeImmutable $expiraEn,
        private ?DateTimeImmutable $cerradaEn,
        private ?string $refreshHash = null,
        private ?DateTimeImmutable $tokenPushActualizadoEn = null,
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
        $tokenEn = $dispositivo?->tokenPush() === null ? null : $iniciadaEn;

        return new self($id, $persona, $dispositivo, $iniciadaEn, $expiraEn, null, null, $tokenEn);
    }

    public static function reconstituir(
        IdDeSesion $id,
        IdDePersona $persona,
        ?Dispositivo $dispositivo,
        DateTimeImmutable $iniciadaEn,
        DateTimeImmutable $expiraEn,
        ?DateTimeImmutable $cerradaEn,
        ?string $refreshHash = null,
        ?DateTimeImmutable $tokenPushActualizadoEn = null,
    ): self {
        return new self($id, $persona, $dispositivo, $iniciadaEn, $expiraEn, $cerradaEn, $refreshHash, $tokenPushActualizadoEn);
    }

    /** Cerrar también desregistra el teléfono de las notificaciones (NP5). */
    public function cerrar(DateTimeImmutable $ahora): void
    {
        $this->cerradaEn ??= $ahora;
        $this->quitarTokenPush();
    }

    /** Sin dispositivo no hay a qué atarle el token: la App lo manda siempre en el login (NP19). */
    public function cambiarTokenPush(TokenPush $token, DateTimeImmutable $ahora): void
    {
        if ($this->dispositivo === null) {
            return;
        }

        $this->dispositivo = $this->dispositivo->conToken($token);
        $this->tokenPushActualizadoEn = $ahora;
    }

    public function quitarTokenPush(): void
    {
        $this->dispositivo = $this->dispositivo?->conToken(null);
        $this->tokenPushActualizadoEn = null;
    }

    public function desvincularDispositivo(): void
    {
        $this->dispositivo = null;
        $this->tokenPushActualizadoEn = null;
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

    public function tokenPush(): ?TokenPush
    {
        return $this->dispositivo?->tokenPush();
    }

    public function tokenPushActualizadoEn(): ?DateTimeImmutable
    {
        return $this->tokenPushActualizadoEn;
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
