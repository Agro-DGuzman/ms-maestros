<?php

declare(strict_types=1);

namespace App\Eventos;

use DateTimeImmutable;
use Exception;
use Identidad\Application\Push\AnunciarAviso\AnunciarAviso;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\CategoriaDeAviso;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;

/**
 * `comercial.aviso.creado` → `AnunciarAviso`, con los límites del contrato de
 * eventos. Lo que no los cumple es un evento que no se entiende: no se
 * completa y termina en mensajes muertos.
 */
final class TraductorDeAviso
{
    public const string TIPO = 'comercial.aviso.creado';

    public static function traducir(Sobre $sobre): AnunciarAviso
    {
        $datos = $sobre->datos;
        $avisoId = self::texto($datos, 'avisoId', 60);

        if (($sobre->clave['avisoId'] ?? null) !== $avisoId) {
            throw EventoInvalido::porDatos('clave.avisoId no coincide con datos.avisoId');
        }

        $categoria = CategoriaDeAviso::tryFrom(is_string($datos['categoria'] ?? null) ? $datos['categoria'] : '');

        if ($categoria === null) {
            throw EventoInvalido::porDatos('categoria no es una de las de la bandeja');
        }

        // Las dos son obligatorias en el contrato, aunque valgan null: una que
        // falta no se lee como null, porque cardCode null es «para todos».
        foreach (['cardCode', 'fechaDelDocumento'] as $obligatorio) {
            if (! array_key_exists($obligatorio, $datos)) {
                throw EventoInvalido::porDatos("falta {$obligatorio}");
            }
        }

        $cardCode = $datos['cardCode'];

        if ($cardCode !== null && (! is_string($cardCode) || trim($cardCode) === '')) {
            throw EventoInvalido::porDatos('cardCode tiene que ser texto o null');
        }

        // Solo el catálogo es para todos: una factura sin socio sonaría en cada teléfono.
        if (($cardCode === null) !== ($categoria === CategoriaDeAviso::Catalogo)) {
            throw EventoInvalido::porDatos('cardCode es null si y solo si el aviso es de catálogo');
        }

        return new AnunciarAviso(new AvisoParaPush(
            $avisoId,
            $categoria,
            $cardCode,
            self::texto($datos, 'titulo', 65),
            self::texto($datos, 'cuerpo', 240),
            self::instante($datos['creadoEn'] ?? null),
            self::fecha($datos['fechaDelDocumento']),
        ));
    }

    /** @param array<array-key, mixed> $datos */
    private static function texto(array $datos, string $campo, int $largo): string
    {
        $valor = $datos[$campo] ?? null;

        if (! is_string($valor) || trim($valor) === '' || mb_strlen($valor) > $largo) {
            throw EventoInvalido::porDatos("{$campo} tiene que ser texto de 1 a {$largo} caracteres");
        }

        return $valor;
    }

    private static function instante(mixed $valor): DateTimeImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/D', $valor) !== 1) {
            throw EventoInvalido::porDatos('creadoEn tiene que ser un instante ISO 8601 con zona');
        }

        try {
            return new DateTimeImmutable($valor);
        } catch (Exception) {
            throw EventoInvalido::porDatos('creadoEn no es un instante válido');
        }
    }

    /** YYYY-MM-DD de un día que existe, o null. */
    private static function fecha(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (! is_string($valor) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $valor, $partes) !== 1
            || ! checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])) {
            throw EventoInvalido::porDatos('fechaDelDocumento tiene que ser YYYY-MM-DD o null');
        }

        return $valor;
    }
}
