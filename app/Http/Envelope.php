<?php

declare(strict_types=1);

namespace App\Http;

use Core\Results\Error;
use Core\Results\FieldError;
use Core\Results\Result;
use Core\Results\ValidationError;
use Illuminate\Http\JsonResponse;

/**
 * El envelope se construye siempre desde un Result, nunca a mano: así
 * `success: true ⟹ error: null` es cierto por construcción.
 */
final class Envelope
{
    /** @return array{data: mixed, success: bool, error: null} */
    public static function exito(mixed $data = null): array
    {
        return ['data' => $data, 'success' => true, 'error' => null];
    }

    /** @return array{data: null, success: bool, error: array<string, mixed>} */
    public static function fallo(Error $error): array
    {
        $errores = $error instanceof ValidationError ? $error->errors() : [$error];

        // El contrato llama `structuredMessage` al desglose por campo, no a la
        // plantilla del mensaje: un error que no es de un campo no aporta nada.
        $porCampo = array_filter($errores, static fn (Error $e): bool => $e instanceof FieldError);

        return [
            'data' => null,
            'success' => false,
            'error' => [
                'code' => array_values(array_unique(array_map(static fn (Error $e): string => $e->code, $errores))),
                'description' => count($errores) === 1 ? $errores[0]->description : $error->description,
                'structuredMessage' => array_values(array_map(
                    static fn (FieldError $e): array => ['campo' => $e->field, 'codigo' => $e->code, 'mensaje' => $e->description],
                    $porCampo,
                )),
                'type' => $error->type->value,
            ],
        ];
    }

    /** @return array{data: mixed, success: bool, error: array<string, mixed>|null} */
    public static function desde(Result $resultado, mixed $data = null): array
    {
        return $resultado->isSuccess ? self::exito($data) : self::fallo($resultado->error);
    }

    public static function responder(Result $resultado, mixed $data = null, int $statusExito = 200): JsonResponse
    {
        return $resultado->isSuccess
            ? new JsonResponse(self::exito($data), $statusExito)
            : new JsonResponse(self::fallo($resultado->error), MapaDeErroresHttp::status($resultado->error));
    }
}
