<?php

declare(strict_types=1);

/*
 * Manda un push de prueba con la cuenta real de Firebase y dice qué respondió.
 * Prueba el proyecto y la cuenta de servicio sin el recorrido del bus: el
 * teléfono no tiene que tener una sesión abierta.
 *
 * Arma EnviadorFirebase directo desde config/push.php y no mira PUSH_DRIVER:
 * con el driver local en `firebase`, cada aviso del bus haría sonar un
 * teléfono de verdad. FIREBASE_PROYECTO y FIREBASE_CUENTA_DE_SERVICIO van en
 * el .env, que git ignora. Cómo correrlo: CLAUDE.md, «El push».
 *
 * El token se pasa como argumento y no se imprime: identifica el teléfono de
 * una persona.
 *
 *   php tests/Manual/probar-push.php <token de registro de Firebase>
 */

use App\Push\EnviadorFirebase;
use Identidad\Application\Contracts\EstadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\CategoriaDeAviso;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Console\Kernel as Consola;
use Illuminate\Http\Client\Factory as Http;
use Psr\Log\AbstractLogger;

$raiz = dirname(__DIR__, 2);

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Consola::class)->bootstrap();

$token = $argv[1] ?? '';

if (trim($token) === '') {
    fwrite(STDERR, "Uso: php tests/Manual/probar-push.php <token de registro de Firebase>\n");
    exit(1);
}

$faltan = array_keys(array_filter([
    'FIREBASE_PROYECTO' => config('push.firebase.proyecto'),
    'FIREBASE_CUENTA_DE_SERVICIO' => config('push.firebase.cuenta_de_servicio'),
], static fn (mixed $valor): bool => ! is_string($valor) || trim($valor) === ''));

if ($faltan !== []) {
    fwrite(STDERR, 'Falta configurar: '.implode(', ', $faltan).".\n");
    exit(1);
}

// El enviador no lanza: lo que pasó se ve en el resultado y en lo que registra.
$log = new class extends AbstractLogger
{
    /** @var list<array{mensaje: string, contexto: array<array-key, mixed>}> */
    public array $registros = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->registros[] = ['mensaje' => (string) $message, 'contexto' => $context];
    }
};

$resultado = (new EnviadorFirebase(
    $app->make(Http::class),
    $app->make(Cache::class),
    (string) config('push.firebase.proyecto'),
    (string) config('push.firebase.cuenta_de_servicio'),
    $log,
))->enviar(TokenPush::desde($token), new AvisoParaPush(
    'AV-PRUEBA-'.date('YmdHis'),
    CategoriaDeAviso::Catalogo,
    null,
    'Prueba de notificaciones',
    'Si ves esto, el push de Agropartners llega a este teléfono.',
    new DateTimeImmutable,
    null,
));

echo 'Proyecto '.config('push.firebase.proyecto').': '.$resultado->estado->name."\n";

foreach ($log->registros as $registro) {
    echo $registro['mensaje'].': '.json_encode($registro['contexto'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
}

if ($resultado->estado === EstadoDeEnvio::Entregado) {
    // Aceptado no es entregado: la prueba es que la notificación aparezca en el teléfono.
    echo "Firebase aceptó el envío. Tiene que aparecer en el teléfono.\n";
    exit(0);
}

exit(1);
