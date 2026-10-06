<?php

declare(strict_types=1);

namespace Tests\Soporte;

/**
 * Lo que manda el Sincronizador por HTTP: las cabeceras completas (con el token
 * de `TokenDeEntra`) y el cuerpo de C-900001 en el formato del contrato.
 */
final class Ingesta
{
    /** @return array<string, string> */
    public static function cabeceras(?string $clave = null, array $sin = []): array
    {
        static $secuencia = 0;
        $secuencia++;

        $cabeceras = [
            'X-Gateway-Secret' => 'secreto-del-gateway',
            'Authorization' => 'Bearer '.TokenDeEntra::valido(),
            // Una clave distinta por pedido, salvo que el test quiera repetirla.
            'Idempotency-Key' => $clave ?? str_pad(dechex($secuencia), 32, '0', STR_PAD_LEFT),
        ];

        return array_diff_key($cabeceras, array_flip($sin));
    }

    /**
     * @param  array<string, mixed>  $sobrescribir
     * @return array<string, mixed>
     */
    public static function cuerpo(array $sobrescribir = []): array
    {
        return array_replace([
            'cardCode' => 'C-900001',
            'razonSocial' => 'Agro Prueba SRL',
            'tipoSap' => 'C',
            'activo' => true,
            'grupoEconomico' => ['codigo' => 'GRP-900', 'nombre' => 'Grupo Prueba', 'segmento' => 'Agroindustrial'],
            'contactos' => [
                ['codigoDeContacto' => '1523', 'nombre' => 'Mónica Salvatierra', 'celular' => '70741828', 'activo' => true],
                ['codigoDeContacto' => '1524', 'nombre' => 'Rodrigo Téllez', 'celular' => '70112233', 'activo' => true],
            ],
            'vigenteDesde' => '2026-10-05T12:00:00Z',
            'origen' => ['esquema' => 'AGRO_P6', 'eventoId' => 1842],
        ], $sobrescribir);
    }
}
