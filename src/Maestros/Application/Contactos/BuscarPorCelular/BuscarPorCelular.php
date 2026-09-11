<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\BuscarPorCelular;

use Core\Contracts\Request;
use Maestros\Domain\Contactos\Celular;

final readonly class BuscarPorCelular implements Request
{
    public function __construct(public Celular $celular) {}
}
