<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\MotivoDeNoIngreso;

/**
 * Las únicas preguntas que Identidad le hace a Maestros. Hoy se resuelven
 * en proceso; el día que se partan los servicios, el adaptador se reemplaza
 * por un cliente HTTP y nada más cambia.
 */
interface DirectorioDeContactos
{
    public function contexto(IdDePersona $id): ?ContextoDeContacto;

    /**
     * Por qué este número no puede entrar a la App, o `null` si entra. Lo usa
     * la habilitación para decirle al operador qué corregir en SAP.
     */
    public function motivoDeNoIngreso(Celular $celular): ?MotivoDeNoIngreso;
}
