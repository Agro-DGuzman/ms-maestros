<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Results\Result;

/**
 * Guarda lo que se despachó y devuelve el `Result` que le dieron. Sirve para
 * comprobar que el back-office delega en el caso de uso de Identidad sin
 * montar Keycloak ni base de datos.
 */
final class MediatorEspia implements Mediator
{
    /** @var list<Request> */
    public array $despachados = [];

    public function __construct(private readonly Result $respuesta) {}

    public function send(Request $peticion): Result
    {
        $this->despachados[] = $peticion;

        return $this->respuesta;
    }

    public function ultimo(): ?Request
    {
        return $this->despachados === [] ? null : $this->despachados[count($this->despachados) - 1];
    }
}
