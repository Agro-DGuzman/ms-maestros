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
}
