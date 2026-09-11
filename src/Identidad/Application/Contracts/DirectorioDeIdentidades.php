<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Core\Results\Result;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Habilitar deja de ser poner una fecha en una fila: es crear el usuario en
 * el directorio. Que pueda quedar habilitado de un lado y no del otro es un
 * estado real, y por eso existe `existe()` y el comando de conciliación.
 */
interface DirectorioDeIdentidades
{
    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result;

    public function deshabilitar(IdDePersona $persona): Result;

    public function existe(IdDePersona $persona): bool;
}
