<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonException;

/**
 * El sobre de la versión 1 que manda el buzón (ED2). Acá solo se valida la forma; qué
 * significa `datos` lo decide el traductor de cada familia.
 */
final readonly class Sobre
{
    /**
     * @param  array<array-key, mixed>  $clave
     * @param  array<array-key, mixed>  $datos
     */
    private function __construct(
        public string $eventoId,
        public string $tipo,
        public DateTimeImmutable $ocurridoEn,
        public ?string $origen,
        public array $clave,
        public int $version,
        public array $datos,
    ) {}

    public static function desdeJson(string $json): self
    {
        try {
            $sobre = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw EventoInvalido::porForma("el cuerpo no es JSON ({$e->getMessage()})");
        }

        if (! is_array($sobre)) {
            throw EventoInvalido::porForma('el cuerpo no es un objeto');
        }

        return self::desdeArreglo($sobre);
    }

    /** @param array<array-key, mixed> $sobre */
    public static function desdeArreglo(array $sobre): self
    {
        $eventoId = $sobre['eventoId'] ?? null;

        if (! is_string($eventoId) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $eventoId) !== 1) {
            throw EventoInvalido::porForma('eventoId tiene que ser un UUID');
        }

        $tipo = $sobre['tipo'] ?? null;

        if (! is_string($tipo) || trim($tipo) === '') {
            throw EventoInvalido::porForma('falta el tipo');
        }

        // Entero y no "1": una versión que llega como texto es un productor
        // que no sigue el contrato, y adivinar qué quiso decir es peor.
        if (($sobre['version'] ?? null) !== 1) {
            throw EventoInvalido::porForma('la única versión que se entiende es la 1');
        }

        $clave = $sobre['clave'] ?? null;
        $datos = $sobre['datos'] ?? null;

        if (! is_array($clave) || ! is_array($datos)) {
            throw EventoInvalido::porForma('clave y datos tienen que ser objetos');
        }

        $origen = $sobre['origen'] ?? null;

        return new self(
            // Es la clave de idempotencia de toda la cadena: el mismo UUID con
            // otras mayúsculas no puede pasar por un evento distinto.
            strtolower($eventoId),
            trim($tipo),
            self::instante($sobre['ocurridoEn'] ?? null),
            is_string($origen) ? $origen : null,
            $clave,
            1,
            $datos,
        );
    }

    /**
     * Con zona explícita: sin ella no se sabe de qué hora habla, y es lo que
     * compara debeReemplazarA(). Hasta microsegundos.
     */
    private static function instante(mixed $texto): DateTimeImmutable
    {
        if (! is_string($texto) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{1,6})?(Z|[+-]\d{2}:\d{2})$/D', $texto) !== 1) {
            throw EventoInvalido::porForma('ocurridoEn tiene que ser un instante ISO 8601 con zona');
        }

        try {
            return (new DateTimeImmutable($texto))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            throw EventoInvalido::porForma("ocurridoEn no es un instante válido: {$texto}");
        }
    }
}
