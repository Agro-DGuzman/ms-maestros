<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

/**
 * La instalación de la App desde la que se abrió una sesión, con el token push
 * si la persona dio permiso de notificaciones. Sin token, la sesión existe
 * igual: la App puede registrarlo después (NP18, NP19).
 */
final readonly class Dispositivo
{
    private function __construct(
        private IdDeInstalacion $instalacion,
        private Plataforma $plataforma,
        private ?TokenPush $tokenPush,
    ) {}

    public static function registrar(IdDeInstalacion $instalacion, Plataforma $plataforma, ?TokenPush $tokenPush = null): self
    {
        return new self($instalacion, $plataforma, $tokenPush);
    }

    public function conToken(?TokenPush $tokenPush): self
    {
        return new self($this->instalacion, $this->plataforma, $tokenPush);
    }

    public function instalacion(): IdDeInstalacion
    {
        return $this->instalacion;
    }

    public function plataforma(): Plataforma
    {
        return $this->plataforma;
    }

    public function tokenPush(): ?TokenPush
    {
        return $this->tokenPush;
    }
}
