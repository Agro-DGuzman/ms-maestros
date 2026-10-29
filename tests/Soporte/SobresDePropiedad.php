<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Ramsey\Uuid\Uuid;

/**
 * El `comercial.propiedad.actualizada` del contrato de eventos (§3 de la spec
 * de ms-comercial): PROP-014 «Lote 14 · San Julián», de C-004871, activa,
 * fotografiada el 21/10 a las 12:00 UTC.
 */
final class SobresDePropiedad
{
    /**
     * @param  array<string, mixed>  $datos  reemplaza campos de `datos`
     * @param  array<string, mixed>  $sobre  reemplaza campos del sobre
     * @return array<string, mixed>
     */
    public static function de(array $datos = [], array $sobre = []): array
    {
        $base = [
            'eventoId' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            'tipo' => 'comercial.propiedad.actualizada',
            'ocurridoEn' => '2026-10-21T12:00:00.000Z',
            'origen' => 'ms-comercial',
            'clave' => ['objeto' => 'propiedad', 'propiedadId' => 'PROP-014'],
            'version' => 1,
            'datos' => array_replace([
                'propiedadId' => 'PROP-014',
                'nombre' => 'Lote 14 · San Julián',
                'zona' => 'norte',
                'cardCode' => 'C-004871',
                'estado' => 'activo',
            ], $datos),
        ];

        return array_replace($base, $sobre);
    }

    /**
     * Otra foto: cada una lleva su propio `eventoId`, como en el bus.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<string, mixed>  $sobre
     * @return array<string, mixed>
     */
    public static function otra(array $datos = [], array $sobre = []): array
    {
        return self::de($datos, ['eventoId' => Uuid::uuid4()->toString()] + $sobre);
    }
}
