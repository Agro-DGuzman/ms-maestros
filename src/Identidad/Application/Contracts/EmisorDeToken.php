<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Hoy lo implementa Keycloak con Direct Access Grant. Ese grant está en camino
 * de deprecación: cuando se retire, la salida es reemplazar esta implementación
 * por una que firme el JWT en Maestros. El dominio, los casos de uso y el
 * contrato no se tocan.
 */
interface EmisorDeToken
{
    /** Envuelve un TokenEmitido cuando tiene éxito. */
    public function emitirPara(IdDePersona $persona, string $contrasena): ResultWithValue;

    /** Envuelve un TokenEmitido cuando tiene éxito. */
    public function renovar(string $refreshToken): ResultWithValue;

    public function revocar(string $refreshToken): Result;
}
