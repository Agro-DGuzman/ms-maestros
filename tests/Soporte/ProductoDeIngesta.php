<?php

declare(strict_types=1);

namespace Tests\Soporte;

use DateTimeImmutable;
use Maestros\Application\Ingesta\CategoriaIngresada;
use Maestros\Application\Ingesta\ProductoIngresado;
use Maestros\Application\Ingesta\RegistroIngresado;

/**
 * El cuerpo que manda el Sincronizador, con el ALVO de 1 L por defecto, y el
 * mismo cuerpo ya armado como DTO: así el test de escritura y el de la ruta
 * hablan del mismo producto.
 */
final class ProductoDeIngesta
{
    /**
     * @param  array<string, mixed>  $sobrescribir
     * @return array<string, mixed>
     */
    public static function cuerpo(array $sobrescribir = []): array
    {
        return array_merge([
            'itemCode' => '10198',
            'nombre' => 'ALVO - 1 L',
            'activo' => true,
            'categoria' => ['codigo' => 'insecticidas', 'nombre' => 'INSECTICIDAS'],
            'presentacion' => '1 L',
            'ingredienteActivo' => 'Bifenthrin 280 g/L + Clothianidin 140 g/L',
            'formulacionCodigo' => 'SC',
            'formulacionDescripcion' => 'SUSPENSION CONCENTRADA',
            'dosisReferencial' => '300 ml/ha, 400 ml/ha',
            'cultivos' => ['ARROZ (Oryza Sativa L.)', 'SOYA (Glycine Max L.)'],
            'registro' => ['entidad' => 'SENASAG', 'numero' => '4848'],
            'vigenteDesde' => '2026-10-16T12:00:00Z',
            'origen' => ['esquema' => 'AGRO_P6', 'eventoId' => 5120],
        ], $sobrescribir);
    }

    /** @param array<string, mixed> $sobrescribir */
    public static function ingresado(array $sobrescribir = []): ProductoIngresado
    {
        $cuerpo = self::cuerpo($sobrescribir);

        $categoria = is_array($cuerpo['categoria'] ?? null)
            ? CategoriaIngresada::de((string) $cuerpo['categoria']['codigo'], (string) $cuerpo['categoria']['nombre'])
            : null;

        $registro = is_array($cuerpo['registro'] ?? null)
            ? new RegistroIngresado((string) $cuerpo['registro']['entidad'], (string) $cuerpo['registro']['numero'])
            : null;

        /** @var array<string, mixed> $origen */
        $origen = $cuerpo['origen'];

        return new ProductoIngresado(
            (string) $cuerpo['itemCode'],
            (string) $cuerpo['nombre'],
            (bool) $cuerpo['activo'],
            $categoria,
            self::texto($cuerpo, 'presentacion'),
            self::texto($cuerpo, 'ingredienteActivo'),
            self::texto($cuerpo, 'formulacionCodigo'),
            self::texto($cuerpo, 'formulacionDescripcion'),
            self::texto($cuerpo, 'dosisReferencial'),
            array_values(array_map('strval', (array) $cuerpo['cultivos'])),
            $registro,
            new DateTimeImmutable((string) $cuerpo['vigenteDesde']),
            (string) $origen['esquema'],
            (int) $origen['eventoId'],
        );
    }

    /** @param array<string, mixed> $cuerpo */
    private static function texto(array $cuerpo, string $clave): ?string
    {
        $valor = $cuerpo[$clave] ?? null;

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }
}
