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
        'NO_AUTENTICADO' => 401,
        'ACCESO_DENEGADO' => 403,
        'LIMITE_DE_TASA' => 429,
    ];

    public static function status(Error $error): int
    {
        return self::POR_CODIGO[$error->code] ?? match ($error->type) {
            ErrorType::Validation => 422,
            ErrorType::NotFound => 404,
            ErrorType::Conflict => 409,
            ErrorType::Problem, ErrorType::Failure => 500,
        };
    }
}
