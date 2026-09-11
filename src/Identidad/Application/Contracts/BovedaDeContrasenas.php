<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\IdDePersona;

/**
 * La contraseña por persona: generada al habilitar, guardada cifrada, rotable.
 * El socio nunca la conoce ni la necesita.
 */
interface BovedaDeContrasenas
{
    public function guardar(IdDePersona $persona, string $contrasena): void;

    public function leer(IdDePersona $persona): ?string;
}
