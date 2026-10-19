<?php

declare(strict_types=1);

namespace App\Http;

use Core\Results\Error;

/**
 * Lo que responde la app cuando un pedido no encuentra ruta. El método y la
 * ruta van en la descripción porque son lo único que dice quién se equivocó:
 * un APIM que reenvía mal se ve en la ruta que llegó.
 */
final class ErroresDeRuta
{
    public static function rutaInexistente(string $metodo, string $ruta): Error
    {
        return Error::notFound('RUTA_INEXISTENTE', 'No existe la ruta {metodo} {ruta}', $metodo, $ruta);
    }

    public static function metodoNoPermitido(string $metodo, string $ruta): Error
    {
        return Error::failure('METODO_NO_PERMITIDO', 'La ruta {ruta} no admite {metodo}', $ruta, $metodo);
    }
}
