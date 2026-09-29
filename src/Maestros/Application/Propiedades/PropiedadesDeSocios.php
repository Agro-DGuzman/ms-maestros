<?php

declare(strict_types=1);

namespace Maestros\Application\Propiedades;

use Maestros\Domain\Socios\CodigoDeSocio;

/**
 * Las propiedades no son réplica de SAP: salen de una conciliación y se
 * cargan por script. Para la App existe solo la propiedad activa, y la lista y
 * el conteo usan esa misma definición: `cantidadPropiedades` no puede decir
 * algo distinto de lo que muestra la lista.
 */
interface PropiedadesDeSocios
{
    /** @return list<PropiedadDelSocio> por nombre y, a igualdad, por id */
    public function deSocio(CodigoDeSocio $socio): array;

    /**
     * Una sola consulta para todos los socios del grupo, no una por socio.
     * Un socio sin propiedades no figura: quien consulta completa con 0.
     *
     * @param  list<CodigoDeSocio>  $socios
     * @return array<string, int> por código de socio
     */
    public function contarPorSocio(array $socios): array;
}
