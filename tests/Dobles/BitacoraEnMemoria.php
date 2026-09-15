<?php

declare(strict_types=1);

namespace Tests\Dobles;

use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;

final class BitacoraEnMemoria implements BitacoraRepository
{
    /** @var list<AsientoDeBitacora> */
    public array $asientos = [];

    public function asentar(AsientoDeBitacora $asiento): void
    {
        $this->asientos[] = $asiento;
    }

    /** @return list<AsientoDeBitacora> */
    public function historialDe(string $idDePersona): array
    {
        return array_values(array_filter(
            $this->asientos,
            static fn (AsientoDeBitacora $a): bool => $a->idDePersona === $idDePersona,
        ));
    }
}
