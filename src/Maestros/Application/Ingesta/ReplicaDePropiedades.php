<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use Maestros\Domain\Propiedades\PropiedadReplicada;

/**
 * Las propiedades, escritas desde los eventos de ms-comercial (EM8). Una
 * réplica nunca retrocede: se escribe si no existe, si nunca llegó por un
 * evento (la semilla) o si la foto es estrictamente más nueva.
 */
interface ReplicaDePropiedades
{
    /** @return bool si escribió */
    public function guardarSiEsMasNueva(PropiedadReplicada $propiedad): bool;
}
