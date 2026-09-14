<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\SolicitarDesafio;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Maestros\Domain\Contactos\Celular;

final readonly class SolicitarDesafio implements Request, RequiereTransaccion
{
    public function __construct(public Celular $celular) {}
}
