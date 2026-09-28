<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/** Los tres tipos que acepta el contrato; cada uno es una columna de URL. */
enum TipoDeDocumento: string
{
    case FichaTecnica = 'ficha_tecnica';
    case HojaDeSeguridad = 'hoja_seguridad';
    case RegistroSanitario = 'registro_sanitario';

    public function etiqueta(): string
    {
        return match ($this) {
            self::FichaTecnica => 'Ficha técnica',
            self::HojaDeSeguridad => 'Hoja de seguridad',
            self::RegistroSanitario => 'Registro sanitario',
        };
    }
}
