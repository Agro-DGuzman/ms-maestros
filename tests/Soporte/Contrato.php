<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

/**
 * Valida una respuesta contra el esquema que el contrato declara para esa
 * operación y ese status. Un status que el contrato no declara también es una
 * diferencia: la App no sabe qué hacer con él.
 */
final class Contrato
{
    private const string ID = 'https://contrato.agropartners/api-v1.json';

    private static ?Validator $validador = null;

    private static ?stdClass $documento = null;

    /**
     * @param  TestResponse<Response>  $respuesta
     * @return list<string> las diferencias; vacía si la respuesta cumple
     */
    public static function diferencias(TestResponse $respuesta, string $metodo, string $ruta): array
    {
        $status = (string) $respuesta->getStatusCode();
        $puntero = self::punteroAlEsquema(strtolower($metodo), $ruta, $status);

        if ($puntero === null) {
            return ["el contrato no declara {$status} para {$metodo} {$ruta}"];
        }

        $resultado = self::validador()->validate(
            json_decode((string) $respuesta->getContent()),
            self::ID.'#'.$puntero,
        );

        if ($resultado->isValid()) {
            return [];
        }

        $diferencias = [];

        foreach ((new ErrorFormatter)->format($resultado->error()) as $lugar => $mensajes) {
            foreach ((array) $mensajes as $mensaje) {
                $diferencias[] = $lugar.': '.$mensaje;
            }
        }

        return $diferencias;
    }

    private static function punteroAlEsquema(string $metodo, string $ruta, string $status): ?string
    {
        $operacion = self::documento()->paths->{$ruta}->{$metodo} ?? null;
        $respuesta = $operacion?->responses->{$status} ?? null;

        if (! $respuesta instanceof stdClass) {
            return null;
        }

        $base = '/paths/'.self::escapar($ruta).'/'.$metodo.'/responses/'.$status;

        // Las respuestas compartidas (401, 403) son una referencia a components.
        if (isset($respuesta->{'$ref'})) {
            $base = substr((string) $respuesta->{'$ref'}, 1);
        }

        return $base.'/content/application~1json/schema';
    }

    private static function escapar(string $segmento): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segmento);
    }

    private static function documento(): stdClass
    {
        if (self::$documento === null) {
            $documento = Yaml::parseFile(
                dirname(__DIR__, 2).'/contrato/agropartners-api-v1.yaml',
                Yaml::PARSE_OBJECT_FOR_MAP,
            );
            assert($documento instanceof stdClass);
            self::$documento = $documento;
        }

        return self::$documento;
    }

    private static function validador(): Validator
    {
        if (self::$validador === null) {
            self::$validador = new Validator;
            self::$validador->setMaxErrors(20);
            self::$validador->resolver()?->registerRaw(self::documento(), self::ID);
        }

        return self::$validador;
    }
}
