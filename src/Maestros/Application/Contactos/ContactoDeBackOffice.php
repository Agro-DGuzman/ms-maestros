<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

use Maestros\Domain\Contactos\AlcanceDeNumero;

/**
 * Una fila de la lista del back-office. Es un modelo de lectura: no valida
 * nada y no se escribe.
 *
 * `celularEsValido` viaja calculado porque la pantalla apaga el interruptor
 * cuando el número no sirve, y ese juicio lo hace el dominio del celular, no
 * la vista.
 */
final readonly class ContactoDeBackOffice
{
    public function __construct(
        public string $idDePersona,
        public string $nombre,
        public string $iniciales,
        public ?string $celular,
        public bool $celularEsValido,
        public string $cardCode,
        public string $razonSocial,
        public ?string $grupoEconomico,
        /** `visible`, `inactivo` o `dado-de-baja`, contando también al socio. */
        public string $estado,
        /**
         * El id de la persona que es este número, o `null` si el celular no
         * sirve. Dar y quitar acceso actúan sobre él, no sobre el contacto.
         */
        public ?string $personaDelNumero,
        /** Los otros contactos visibles con el mismo número: son la misma persona. */
        public int $compartidoCon,
        /** Si el número entra y por qué no; `null` cuando el celular no sirve. */
        public ?AlcanceDeNumero $alcanceDelNumero,
    ) {}
}
