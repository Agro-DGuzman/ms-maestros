<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ProcesarEvento;

use Core\Results\DomainException;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Maestros\Domain\Propiedades\PropiedadReplicada;

/**
 * `comercial.propiedad.actualizada` a la propiedad (ED5). La foto es
 * completa: un campo obligatorio que falta es un evento roto, no «sin
 * cambios». `zona` se ignora, porque la App no se la pide a ms-maestros.
 */
final class TraductorDePropiedad
{
    public const string TIPO = 'comercial.propiedad.actualizada';

    public static function traducir(Sobre $sobre): PropiedadReplicada
    {
        $datos = $sobre->datos;
        $id = self::texto($datos, 'propiedadId');

        if (($sobre->clave['propiedadId'] ?? null) !== $id) {
            throw EventoInvalido::porDatos('clave.propiedadId no coincide con datos.propiedadId');
        }

        try {
            return PropiedadReplicada::desde(
                $id,
                self::texto($datos, 'nombre'),
                self::texto($datos, 'cardCode'),
                self::texto($datos, 'estado'),
                $sobre->ocurridoEn,
            );
        } catch (DomainException $e) {
            throw EventoInvalido::porDatos($e->getMessage());
        }
    }

    /** @param array<array-key, mixed> $datos */
    private static function texto(array $datos, string $campo): string
    {
        $valor = $datos[$campo] ?? null;

        if (! is_string($valor)) {
            throw EventoInvalido::porDatos("falta {$campo} o no es texto");
        }

        return $valor;
    }
}
