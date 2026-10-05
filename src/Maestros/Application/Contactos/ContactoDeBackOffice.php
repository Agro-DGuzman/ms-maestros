<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

use DateTimeImmutable;

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
        public bool $estaHabilitada,
        public ?DateTimeImmutable $habilitadaEl,
        /** `visible`, `inactivo` o `dado-de-baja`, contando también al socio. */
        public string $estado = 'visible',
        /**
         * Tiene habilitación pero la App ya no la ve. No la deshabilita nadie
         * desde acá: la pantalla avisa y una persona decide.
         */
        public bool $dadoDeBajaEnSap = false,
        /** Otro contacto visible tiene el mismo celular: nadie puede entrar con él. */
        public bool $celularEnConflicto = false,
    ) {}
}
