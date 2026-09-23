<?php

declare(strict_types=1);

namespace App\Http;

use Core\Results\Error;
use Core\Results\ErrorType;

final class MapaDeErroresHttp
{
    /**
     * `FAILURE` se parte en tres según el código —401, 403, 429—, así que
     * esta tabla los nombra y el tipo solo da el defecto. Un `FAILURE` que no
     * está acá es un error nuestro, no del cliente: 500, no 403.
     *
     * @var array<string, int>
     */
    private const POR_CODIGO = [
        'TOKEN_INVALIDO' => 401,
        'CODIGO_INVALIDO' => 401,
        'REFRESH_TOKEN_INVALIDO' => 401,
        'ACCESO_DENEGADO' => 403,
        'LIMITE_TASA_SUPERADO' => 429,
    ];

    public static function status(Error $error): int
    {
        // El contrato reserva 422 para una regla de negocio violada; una
        // petición mal formada es 400.
        return self::POR_CODIGO[$error->code] ?? match ($error->type) {
            ErrorType::Validation => 400,
            ErrorType::NotFound => 404,
            ErrorType::Conflict => 409,
            ErrorType::Problem, ErrorType::Failure => 500,
        };
    }
}
