<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use Core\Results\Error;

final class IngestaErrors
{
    /** El Sincronizador lo reintenta en el acto como PUT. */
    public static function socioYaExiste(string $cardCode): Error
    {
        return Error::conflict('SOCIO_YA_EXISTE', 'El socio {cardCode} ya existe', $cardCode);
    }

    public static function cardCodeNoCoincide(): Error
    {
        return Error::validation(
            'CARDCODE_NO_COINCIDE',
            'El cardCode del cuerpo no coincide con el de la ruta',
        );
    }

    /** El Sincronizador lo reintenta en el acto como PUT. */
    public static function productoYaExiste(string $itemCode): Error
    {
        return Error::conflict('PRODUCTO_YA_EXISTE', 'El producto {itemCode} ya existe', $itemCode);
    }

    /** En un PUT el Sincronizador reintenta como POST; en un DELETE lo toma como éxito. */
    public static function productoNoEncontrado(string $itemCode): Error
    {
        return Error::notFound('PRODUCTO_NO_ENCONTRADO', 'No hay un producto {itemCode}', $itemCode);
    }

    public static function itemCodeNoCoincide(): Error
    {
        return Error::validation(
            'ITEMCODE_NO_COINCIDE',
            'El itemCode del cuerpo no coincide con el de la ruta',
        );
    }
}
