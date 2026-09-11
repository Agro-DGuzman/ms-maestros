<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

/** La instalación concreta de la App desde la que se abrió una sesión. */
final readonly class Dispositivo
{
    private function __construct(
        private IdDeInstalacion $instalacion,
        private Plataforma $plataforma,
    ) {}

    public static function registrar(IdDeInstalacion $instalacion, Plataforma $plataforma): self
    {
        return new self($instalacion, $plataforma);
    }

    public function instalacion(): IdDeInstalacion
    {
        return $this->instalacion;
    }

    public function plataforma(): Plataforma
    {
        return $this->plataforma;
    }
}
