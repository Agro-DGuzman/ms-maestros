<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use DateTimeImmutable;
use DateTimeZone;
use Maestros\Application\Ingesta\ProductoAnunciado;
use Ramsey\Uuid\Uuid;

/**
 * «Hay un producto nuevo»: ms-comercial lo convierte en un aviso de catálogo
 * para todos, y ese aviso vuelve como push (NP10). Sale una sola vez por
 * producto, por el outbox, en la transacción de la ingesta que lo vuelve
 * visible (NP11).
 */
final class ProductoNuevo
{
    public const string TIPO = 'maestros.producto.nuevo';

    /** El largo que admite el contrato; ms-comercial corta, pero el sobre tiene que validar igual. */
    private const int LARGO_DEL_NOMBRE = 200;

    public static function de(ProductoAnunciado $producto, DateTimeImmutable $vigenteDesde): EventoSaliente
    {
        $utc = new DateTimeZone('UTC');
        $eventoId = Uuid::uuid4()->toString();

        $sobre = [
            'eventoId' => $eventoId,
            'tipo' => self::TIPO,
            // La vigencia del envío de SAP: ms-comercial solo anuncia lo de los últimos 7 días.
            'ocurridoEn' => $vigenteDesde->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z'),
            'origen' => 'ms-maestros',
            'clave' => ['objeto' => 'producto', 'itemCode' => $producto->itemCode],
            'version' => 1,
            'datos' => [
                'itemCode' => $producto->itemCode,
                'nombre' => mb_substr($producto->nombre, 0, self::LARGO_DEL_NOMBRE),
                'categoria' => ['codigo' => $producto->categoriaCodigo, 'nombre' => $producto->categoriaNombre],
            ],
        ];

        return new EventoSaliente($eventoId, self::TIPO, $sobre, new DateTimeImmutable('now', $utc));
    }
}
