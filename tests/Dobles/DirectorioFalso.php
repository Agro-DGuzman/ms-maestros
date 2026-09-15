<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Modela lo que hace Keycloak: deshabilitar **no borra** al usuario, le pone
 * `enabled = false`. Un doble que hiciera `unset()` afirmaría que el usuario
 * desapareció, y con eso tapó durante un tiempo que la conciliación daba el
 * visto bueno sobre gente que ya no podía entrar.
 */
final class DirectorioFalso implements DirectorioDeIdentidades
{
    public bool $caido = false;

    /** @var array<string, string> */
    public array $usuarios = [];

    /** @var array<string, bool> */
    public array $activos = [];

    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        $this->usuarios[$persona->value()] = $contrasena;
        $this->activos[$persona->value()] = true;

        return Result::success();
    }

    public function deshabilitar(IdDePersona $persona): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        if (isset($this->usuarios[$persona->value()])) {
            $this->activos[$persona->value()] = false;
        }

        return Result::success();
    }

    public function estaActivo(IdDePersona $persona): bool
    {
        return $this->activos[$persona->value()] ?? false;
    }
}
