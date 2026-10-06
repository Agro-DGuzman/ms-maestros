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
    /** @var array<string, Validator> uno por archivo de contrato */
    private static array $validadores = [];

    /** @var array<string, stdClass> */
    private static array $documentos = [];

    /**
     * @param  TestResponse<Response>  $respuesta
     * @return list<string> las diferencias; vacía si la respuesta cumple
     */
    public static function diferencias(TestResponse $respuesta, string $metodo, string $ruta, string $archivo = 'agropartners-api-v1.yaml'): array
    {
        $status = (string) $respuesta->getStatusCode();
        $puntero = self::punteroAlEsquema($archivo, strtolower($metodo), $ruta, $status);

        if ($puntero === null) {
            return ["el contrato no declara {$status} para {$metodo} {$ruta}"];
        }

        $resultado = self::validador($archivo)->validate(
            json_decode((string) $respuesta->getContent()),
            self::id($archivo).'#'.$puntero,
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

    private static function punteroAlEsquema(string $archivo, string $metodo, string $ruta, string $status): ?string
    {
        $operacion = self::documento($archivo)->paths->{$ruta}->{$metodo} ?? null;
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

    /**
     * Primero el escape de JSON Pointer; después el de URI, porque el puntero
     * viaja como fragmento y las llaves de `/productos/{itemCode}` no son
     * válidas ahí.
     */
    private static function escapar(string $segmento): string
    {
        return rawurlencode(str_replace(['~', '/'], ['~0', '~1'], $segmento));
    }

    private static function documento(string $archivo): stdClass
    {
        if (! isset(self::$documentos[$archivo])) {
            $documento = Yaml::parseFile(
                dirname(__DIR__, 2).'/contrato/'.$archivo,
                Yaml::PARSE_OBJECT_FOR_MAP,
            );
            assert($documento instanceof stdClass);
            self::$documentos[$archivo] = $documento;
        }

        return self::$documentos[$archivo];
    }

    /** Cada contrato con su propio id: los `$ref` internos se resuelven contra el suyo. */
    private static function id(string $archivo): string
    {
        return 'https://contrato.agropartners/'.pathinfo($archivo, PATHINFO_FILENAME).'.json';
    }

    private static function validador(string $archivo): Validator
    {
        if (! isset(self::$validadores[$archivo])) {
            $validador = new Validator;
            $validador->setMaxErrors(20);
            $validador->resolver()?->registerRaw(self::documento($archivo), self::id($archivo));
            self::$validadores[$archivo] = $validador;
        }

        return self::$validadores[$archivo];
    }
}
