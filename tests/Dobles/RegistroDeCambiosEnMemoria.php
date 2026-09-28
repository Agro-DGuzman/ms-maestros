<?php

declare(strict_types=1);

namespace Tests\Dobles;

use BackOffice\Domain\Catalogo\CambioDeCatalogo;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;

final class RegistroDeCambiosEnMemoria implements RegistroDeCambiosDelCatalogo
{
    /** @var list<CambioDeCatalogo> */
    public array $cambios = [];

    public function asentar(CambioDeCatalogo $cambio): void
    {
        $this->cambios[] = $cambio;
    }

    /** @return list<CambioDeCatalogo> */
    public function historialDe(int $idProducto): array
    {
        return array_values(array_reverse(array_filter(
            $this->cambios,
            static fn (CambioDeCatalogo $c): bool => $c->idProducto === $idProducto,
        )));
    }
}
