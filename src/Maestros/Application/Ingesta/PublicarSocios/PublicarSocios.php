<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\PublicarSocios;

use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;

/**
 * Pone en el outbox la foto de cada socio que ms-comercial tiene que tener,
 * una vez, en la puesta en marcha (EM5). Transaccional: o entran todos, o
 * ninguno, y correrlo de nuevo no deja una cola a medias.
 */
final readonly class PublicarSocios implements EscrituraDeMaquina, Request, RequiereTransaccion {}
