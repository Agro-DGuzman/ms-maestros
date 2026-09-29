<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Persistence;

use BackOffice\Domain\Catalogo\CambioDeCatalogo;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;
use BackOffice\Domain\Operadores\IdDeOperador;

final class EloquentRegistroDeCambiosDelCatalogo implements RegistroDeCambiosDelCatalogo
{
    public function asentar(CambioDeCatalogo $cambio): void
    {
        CambioDeCatalogoRecord::query()->create([
            'id_de_cambio' => $cambio->idDeCambio,
            'id_de_operador' => $cambio->idDeOperador->valor,
            'operador' => $cambio->operador,
            'id_producto' => $cambio->idProducto,
            'item_code' => $cambio->itemCode,
            'campo' => $cambio->campo,
            'valor_anterior' => $cambio->anterior,
            'valor_nuevo' => $cambio->nuevo,
            'ocurrio_el' => $cambio->ocurrioEl->format('Y-m-d H:i:s'),
            'direccion_ip' => $cambio->direccionIp,
        ]);
    }

    /** @return list<CambioDeCatalogo> */
    public function historialDe(int $idProducto): array
    {
        return array_values(
            CambioDeCatalogoRecord::query()
                ->where('id_producto', $idProducto)
                ->orderByDesc('ocurrio_el')
                ->get()
                ->map(static fn (CambioDeCatalogoRecord $fila): CambioDeCatalogo => CambioDeCatalogo::reconstituir(
                    idDeCambio: $fila->id_de_cambio,
                    idDeOperador: IdDeOperador::desdeOid($fila->id_de_operador),
                    operador: $fila->operador,
                    idProducto: $fila->id_producto,
                    itemCode: $fila->item_code,
                    campo: $fila->campo,
                    anterior: $fila->valor_anterior,
                    nuevo: $fila->valor_nuevo,
                    ocurrioEl: $fila->ocurrio_el->toDateTimeImmutable(),
                    direccionIp: $fila->direccion_ip,
                ))
                ->all(),
        );
    }
}
