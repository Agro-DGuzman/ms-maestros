<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Persistence;

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use BackOffice\Domain\Operadores\IdDeOperador;

final class EloquentBitacoraRepository implements BitacoraRepository
{
    public function asentar(AsientoDeBitacora $asiento): void
    {
        AsientoRecord::query()->create([
            'id_de_asiento' => $asiento->idDeAsiento,
            'id_de_operador' => $asiento->idDeOperador->valor,
            'operador' => $asiento->operador,
            'id_de_persona' => $asiento->idDePersona,
            'accion' => $asiento->accion->value,
            'ocurrio_el' => $asiento->ocurrioEl->format('Y-m-d H:i:s'),
            'direccion_ip' => $asiento->direccionIp,
        ]);
    }

    /** @return list<AsientoDeBitacora> */
    public function historialDe(string $idDePersona): array
    {
        return array_values(
            AsientoRecord::query()
                ->where('id_de_persona', $idDePersona)
                ->orderByDesc('ocurrio_el')
                ->get()
                ->map(static fn (AsientoRecord $fila): AsientoDeBitacora => AsientoDeBitacora::reconstituir(
                    idDeAsiento: $fila->id_de_asiento,
                    idDeOperador: IdDeOperador::desdeOid($fila->id_de_operador),
                    operador: $fila->operador,
                    idDePersona: $fila->id_de_persona,
                    accion: AccionDeAcceso::from($fila->accion),
                    ocurrioEl: $fila->ocurrio_el->toDateTimeImmutable(),
                    direccionIp: $fila->direccion_ip,
                ))
                ->all(),
        );
    }
}
