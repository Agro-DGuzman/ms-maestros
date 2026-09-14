<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Results\Error;
use Core\Results\Result;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Maestros\Domain\Contactos\IdDePersona;

final class DirectorioFalso implements DirectorioDeIdentidades
{
    public bool $caido = false;

    /** @var array<string, string> */
    public array $usuarios = [];

    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        $this->usuarios[$persona->value()] = $contrasena;

        return Result::success();
    }

    public function deshabilitar(IdDePersona $persona): Result
    {
        if ($this->caido) {
            return Result::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'No responde'),
            );
        }

        unset($this->usuarios[$persona->value()]);

        return Result::success();
    }

    public function existe(IdDePersona $persona): bool
    {
        return isset($this->usuarios[$persona->value()]);
    }
}
