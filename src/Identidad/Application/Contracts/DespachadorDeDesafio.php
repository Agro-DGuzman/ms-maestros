<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\Celular;

/**
 * Difiere el envío fuera del ciclo de la petición. Existe para que
 * `POST /auth/otp` tarde lo mismo exista o no el número: resolver la persona
 * y hablar con WhatsApp son las dos cosas caras, y las dos pasan acá adentro.
 */
interface DespachadorDeDesafio
{
    public function despachar(Celular $celular, string $digitos): void;
}
