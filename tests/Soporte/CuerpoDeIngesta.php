<?php

declare(strict_types=1);

namespace Tests\Soporte;

use DateTimeImmutable;
use Maestros\Application\Ingesta\ContactoIngresado;
use Maestros\Application\Ingesta\GrupoIngresado;
use Maestros\Application\Ingesta\SocioIngresado;

/**
 * El socio que manda el Sincronizador en los tests de los casos de uso:
 * C-900001 «Agro Prueba SRL», cliente activo del grupo GRP-900, con Mónica
 * (1523, 70741828) y Rodrigo (1524, 70112233), leído el 5/10 a las 12:00 UTC.
 */
final class CuerpoDeIngesta
{
    /**
     * @param  list<ContactoIngresado>|null  $contactos
     */
    public static function socio(
        string $cardCode = 'C-900001',
        string $razonSocial = 'Agro Prueba SRL',
        string $tipoSap = 'C',
        bool $activo = true,
        ?GrupoIngresado $grupo = new GrupoIngresado('GRP-900', 'Grupo Prueba', 'Agroindustrial'),
        ?array $contactos = null,
        string $vigenteDesde = '2026-10-05T12:00:00Z',
    ): SocioIngresado {
        return new SocioIngresado(
            cardCode: $cardCode,
            razonSocial: $razonSocial,
            tipoSap: $tipoSap,
            activo: $activo,
            grupo: $grupo,
            contactos: $contactos ?? [
                new ContactoIngresado('1523', 'Mónica Salvatierra', '70741828', true),
                new ContactoIngresado('1524', 'Rodrigo Téllez', '70112233', true),
            ],
            vigenteDesde: new DateTimeImmutable($vigenteDesde),
            origenEsquema: 'AGRO_P6',
            origenEventoId: 1842,
        );
    }
}
