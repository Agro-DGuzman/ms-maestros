<?php

declare(strict_types=1);

/*
 * Llama a /ingesta como el Sincronizador, sin Azure. El pedido recorre el
 * pipeline real —secreto del gateway, token, idempotencia, controlador y
 * base— y lo único simulado es Entra, con el mismo doble que usan los tests.
 * Escribe de verdad en la base del entorno: por eso se niega a correr contra
 * Azure SQL.
 *
 * Vive en tests/ para que nunca viaje en la imagen (.dockerignore la excluye),
 * así que en docker se corre montándola. Cómo y contra qué base: CLAUDE.md,
 * «La ingesta, sin Azure».
 *
 *   php tests/Manual/probar-ingesta.php POST   tests/Manual/ingesta/socio-nuevo.json
 *   php tests/Manual/probar-ingesta.php PUT    C-900001 tests/Manual/ingesta/socio-nuevo.json
 *   php tests/Manual/probar-ingesta.php DELETE C-900001
 *
 * Opciones:
 *   --clave=<32 hex>     repetir un pedido (por defecto, una clave nueva)
 *   --vigencia=<ISO>     reemplaza la vigenteDesde del archivo
 *   --sin-secreto        no manda X-Gateway-Secret
 *   --token=vencido|sin-rol|otra-audiencia|ninguno
 */

use Illuminate\Contracts\Console\Kernel as Consola;
use Illuminate\Contracts\Http\Kernel as Http;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Tests\Soporte\TokenDeEntra;

$raiz = dirname(__DIR__, 2);

require $raiz.'/vendor/autoload.php';
require $raiz.'/tests/Soporte/TokenDeEntra.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Consola::class)->bootstrap();

$conexion = config('database.default');
$host = (string) config("database.connections.{$conexion}.host", '');

if (str_contains($host, 'database.windows.net')) {
    fwrite(STDERR, "Esta base es Azure SQL ({$host}). La herramienta escribe de verdad: correla contra la local.\n");
    exit(1);
}

$opciones = [];
$posicionales = [];

foreach (array_slice($argv, 1) as $argumento) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argumento, $m) === 1) {
        $opciones[$m[1]] = $m[2] ?? true;
    } else {
        $posicionales[] = $argumento;
    }
}

$metodo = strtoupper($posicionales[0] ?? '');

[$ruta, $archivo] = match ($metodo) {
    'POST' => ['/ingesta/v1/socios', $posicionales[1] ?? null],
    'PUT' => ['/ingesta/v1/socios/'.($posicionales[1] ?? ''), $posicionales[2] ?? null],
    'DELETE' => ['/ingesta/v1/socios/'.($posicionales[1] ?? ''), null],
    default => [null, null],
};

if ($ruta === null || str_ends_with($ruta, '/') || ($metodo !== 'DELETE' && $archivo === null)) {
    fwrite(STDERR, "Uso: POST <cuerpo.json> | PUT <cardCode> <cuerpo.json> | DELETE <cardCode>\n");
    exit(1);
}

$cuerpo = null;

if ($archivo !== null) {
    $datos = json_decode((string) file_get_contents($archivo), true, flags: JSON_THROW_ON_ERROR);

    if (is_string($opciones['vigencia'] ?? null)) {
        $datos['vigenteDesde'] = $opciones['vigencia'];
    }

    $cuerpo = json_encode($datos, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

TokenDeEntra::configurar();

$token = match ($opciones['token'] ?? 'valido') {
    'valido' => TokenDeEntra::valido(),
    'vencido' => TokenDeEntra::valido(['nbf' => time() - 7200, 'exp' => time() - 3600]),
    'sin-rol' => TokenDeEntra::valido(['roles' => []]),
    'otra-audiencia' => TokenDeEntra::valido(['aud' => 'otra-api']),
    'ninguno' => null,
    default => exit("--token admite vencido, sin-rol, otra-audiencia o ninguno\n"),
};

$clave = is_string($opciones['clave'] ?? null) ? $opciones['clave'] : bin2hex(random_bytes(16));

$cabeceras = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $clave];

if (! isset($opciones['sin-secreto'])) {
    $cabeceras['HTTP_X_GATEWAY_SECRET'] = 'secreto-del-gateway';
}

if ($token !== null) {
    $cabeceras['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
}

// Lo que el controlador decidió (aplicado, ignorado-por-viejo…) y el motivo de
// un token rechazado van al log, no a la respuesta.
$registro = [];
$app['events']->listen(MessageLogged::class, function (MessageLogged $e) use (&$registro): void {
    $registro[] = "log {$e->level}: {$e->message} ".json_encode($e->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

$kernel = $app->make(Http::class);
$pedido = Request::create($ruta, $metodo, [], [], [], $cabeceras, $cuerpo);
$respuesta = $kernel->handle($pedido);

echo "{$metodo} {$ruta}\nIdempotency-Key: {$clave}\n\n{$respuesta->getStatusCode()}\n";

$contenido = (string) $respuesta->getContent();
$json = json_decode($contenido, true);
echo $contenido === '' ? "(sin cuerpo)\n" : (is_array($json) ? json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $contenido)."\n";

$kernel->terminate($pedido, $respuesta);

if ($registro !== []) {
    echo PHP_EOL.implode(PHP_EOL, $registro).PHP_EOL;
}
