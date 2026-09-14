<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Las tres únicas preguntas que Identidad le hace a Maestros. Hoy se resuelven
 * en proceso; el día que se partan los servicios, el adaptador se reemplaza
 * por un cliente HTTP y nada más cambia.
 */
interface DirectorioDeContactos
{
    public function buscarPorCelular(Celular $celular): ?IdDePersona;

    public function contexto(IdDePersona $id): ?ContextoDeContacto;

    /**
     * Las personas a las que Agropartners ya les concedió acceso. La necesita
     * la conciliación, y va acá y no por SQL para que no haya una sola
     * consulta que cruce de un esquema al otro.
     *
     * @return list<IdDePersona>
     */
    public function habilitadas(): array;
}
