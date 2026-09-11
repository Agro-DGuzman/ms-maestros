<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Las dos únicas preguntas que Identidad le hace a Maestros. Hoy se resuelven
 * en proceso; el día que se partan los servicios, el adaptador se reemplaza
 * por un cliente HTTP y nada más cambia.
 */
interface DirectorioDeContactos
{
    public function buscarPorCelular(Celular $celular): ?IdDePersona;

    public function contexto(IdDePersona $id): ?ContextoDeContacto;
}
