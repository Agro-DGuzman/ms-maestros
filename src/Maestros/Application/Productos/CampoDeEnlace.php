<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * Los cuatro campos del catálogo que no vienen de SAP y se administran a mano.
 * El valor es el nombre de la columna y también el del campo del formulario:
 * así un error de validación llega pegado al campo que lo causó.
 */
enum CampoDeEnlace: string
{
    case Imagen = 'imagen_url';
    case FichaTecnica = 'ficha_tecnica_url';
    case HojaDeSeguridad = 'hoja_seguridad_url';
    case RegistroSanitario = 'registro_sanitario_url';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Imagen => 'Imagen',
            self::FichaTecnica => 'Ficha técnica',
            self::HojaDeSeguridad => 'Hoja de seguridad',
            self::RegistroSanitario => 'Registro sanitario',
        };
    }

    public function esImagen(): bool
    {
        return $this === self::Imagen;
    }
}
