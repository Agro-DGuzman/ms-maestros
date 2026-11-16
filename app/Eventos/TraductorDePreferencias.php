<?php

declare(strict_types=1);

namespace App\Eventos;

use Identidad\Application\Push\RegistrarPreferencias\RegistrarPreferencias;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;

/**
 * `comercial.preferencias.actualizadas` → `RegistrarPreferencias`, con los
 * límites del contrato de eventos. Lo que no los cumple es un evento que no
 * se entiende: no se completa y termina en mensajes muertos.
 */
final class TraductorDePreferencias
{
    public const string TIPO = 'comercial.preferencias.actualizadas';

    private const int LARGO_DE_LA_PERSONA = 100;

    public static function traducir(Sobre $sobre): RegistrarPreferencias
    {
        $datos = $sobre->datos;
        $persona = $datos['persona'] ?? null;

        if (! is_string($persona) || trim($persona) === '' || mb_strlen($persona) > self::LARGO_DE_LA_PERSONA) {
            throw EventoInvalido::porDatos('persona tiene que ser texto de 1 a 100 caracteres');
        }

        if (($sobre->clave['persona'] ?? null) !== $persona) {
            throw EventoInvalido::porDatos('clave.persona no coincide con datos.persona');
        }

        return new RegistrarPreferencias(
            $persona,
            self::interruptor($datos, 'facturas'),
            self::interruptor($datos, 'pagos'),
            self::interruptor($datos, 'reportes'),
            self::interruptor($datos, 'catalogo'),
            $sobre->ocurridoEn,
        );
    }

    /** @param array<array-key, mixed> $datos */
    private static function interruptor(array $datos, string $campo): bool
    {
        $valor = $datos[$campo] ?? null;

        if (! is_bool($valor)) {
            throw EventoInvalido::porDatos("{$campo} tiene que ser true o false");
        }

        return $valor;
    }
}
