<?php

declare(strict_types=1);

namespace BackOffice\Domain\Catalogo;

/**
 * Solo anexa y consulta, igual que la bitácora de accesos: si se pudiera
 * editar, no serviría para saber quién cambió un enlace.
 */
interface RegistroDeCambiosDelCatalogo
{
    public function asentar(CambioDeCatalogo $cambio): void;

    /** @return list<CambioDeCatalogo> más reciente primero */
    public function historialDe(int $idProducto): array;
}
