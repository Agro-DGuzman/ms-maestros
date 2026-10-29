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
        $metodo = strtolower($metodo);
        $declarada = self::respuestaDeclarada($archivo, $metodo, $ruta, $status);

        if ($declarada === null) {
            return ["el contrato no declara {$status} para {$metodo} {$ruta}"];
        }

        // Un status declarado sin `content` —el 204 de una baja— cumple si no
        // trae cuerpo: no hay esquema contra el que validar, y un cuerpo donde
        // el contrato no declara ninguno también es una diferencia.
        if (! isset($declarada->{'$ref'}) && ! isset($declarada->content)) {
            $cuerpo = (string) $respuesta->getContent();

            return $cuerpo === '' ? [] : ["el contrato declara {$status} sin cuerpo para {$metodo} {$ruta}"];
        }

        $puntero = self::punteroAlEsquema($archivo, $metodo, $ruta, $status);

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

    /**
     * Valida un dato cualquiera —un evento del bus, que no es una respuesta
     * HTTP— contra un esquema de `components.schemas`.
     *
     * @return list<string> las diferencias; vacía si cumple
     */
    public static function diferenciasDeEsquema(mixed $dato, string $archivo, string $esquema): array
    {
        $resultado = self::validador($archivo)->validate(
            json_decode((string) json_encode($dato, JSON_THROW_ON_ERROR)),
            self::id($archivo).'#/components/schemas/'.self::escapar($esquema),
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

    private static function respuestaDeclarada(string $archivo, string $metodo, string $ruta, string $status): ?stdClass
    {
        $operacion = self::documento($archivo)->paths->{$ruta}->{$metodo} ?? null;
        $respuesta = $operacion?->responses->{$status} ?? null;

        return $respuesta instanceof stdClass ? $respuesta : null;
    }

    private static function punteroAlEsquema(string $archivo, string $metodo, string $ruta, string $status): ?string
    {
        $respuesta = self::respuestaDeclarada($archivo, $metodo, $ruta, $status);

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
