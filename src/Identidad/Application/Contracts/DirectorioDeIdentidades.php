<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Core\Results\Result;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Habilitar deja de ser poner una fecha en una fila: es crear el usuario en
 * el directorio. Que pueda quedar habilitado de un lado y no del otro es un
 * estado real, y por eso existe `estaActivo()` y el comando de conciliación.
 */
interface DirectorioDeIdentidades
{
    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result;

    public function deshabilitar(IdDePersona $persona): Result;

    /**
     * Si la persona puede entrar, que no es lo mismo que si figura. Keycloak
     * no borra al deshabilitar: le pone `enabled = false` y el usuario sigue
     * apareciendo en la búsqueda. Preguntar solo por la existencia daba por
     * bueno a quien ya había perdido el acceso.
     */
    public function estaActivo(IdDePersona $persona): bool;
}
