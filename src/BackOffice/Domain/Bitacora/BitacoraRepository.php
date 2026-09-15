<?php

declare(strict_types=1);

namespace BackOffice\Domain\Bitacora;

/**
 * Solo anexa y consulta. La ausencia de `modificar` y `eliminar` es la
 * garantía: una bitácora que se puede editar no sirve para lo único que sirve
 * una bitácora.
 */
interface BitacoraRepository
{
    public function asentar(AsientoDeBitacora $asiento): void;

    /** @return list<AsientoDeBitacora> más reciente primero */
    public function historialDe(string $idDePersona): array;
}
