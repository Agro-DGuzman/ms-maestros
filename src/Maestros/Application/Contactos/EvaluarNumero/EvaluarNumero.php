<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\EvaluarNumero;

use Core\Contracts\Request;
use Maestros\Domain\Contactos\Celular;

/** Si un número puede entrar a la App y qué socios vería. */
final readonly class EvaluarNumero implements Request
{
    public function __construct(public Celular $celular) {}
}
