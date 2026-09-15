<?php

declare(strict_types=1);

namespace BackOffice\Domain\Bitacora;

enum AccionDeAcceso: string
{
    case Concedio = 'concedio';
    case Revoco = 'revoco';

    public function comoTexto(): string
    {
        return match ($this) {
            self::Concedio => 'concedió el acceso',
            self::Revoco => 'quitó el acceso',
        };
    }
}
