<?php

declare(strict_types=1);

namespace App\Http;

use Core\Results\Error;
use Core\Results\ErrorType;

final class MapaDeErroresHttp
{
    /**
     * `FAILURE` se parte en tres según el código, así que el tipo da el
     * valor por defecto y esta tabla lo corrige donde hace falta.
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
            ErrorType::Problem => 500,
            ErrorType::Failure => 403,
        };
    }
}
