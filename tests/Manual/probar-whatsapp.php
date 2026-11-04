<?php

declare(strict_types=1);

/*
 * Manda un código de prueba por WhatsApp con la plantilla real y dice qué
 * respondió Meta. Prueba la plantilla y el token sin el recorrido del
 * ingreso: el número no tiene que ser contacto de ningún socio.
 *
 * Arma EnviadorCloudApi directo desde config/whatsapp.php y no mira
 * WHATSAPP_DRIVER: con el driver local en `cloud_api`, cada /auth/otp
 * mandaría un WhatsApp de verdad. WHATSAPP_PHONE_NUMBER_ID y WHATSAPP_TOKEN
 * van en el .env, que git ignora. Cómo correrlo: CLAUDE.md, «WhatsApp».
 *
 *   php tests/Manual/probar-whatsapp.php 70741828
 */

use Identidad\Infrastructure\Whatsapp\EnviadorCloudApi;
use Illuminate\Contracts\Console\Kernel as Consola;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\Celular;
use Psr\Log\AbstractLogger;

$raiz = dirname(__DIR__, 2);

require $raiz.'/vendor/autoload.php';

$app = require $raiz.'/bootstrap/app.php';
$app->make(Consola::class)->bootstrap();

$numero = $argv[1] ?? '';

if ($numero === '') {
    fwrite(STDERR, "Uso: php tests/Manual/probar-whatsapp.php <celular sin +591>\n");
    exit(1);
}

$faltan = array_keys(array_filter([
    'WHATSAPP_PHONE_NUMBER_ID' => config('whatsapp.phone_number_id'),
    'WHATSAPP_TOKEN' => config('whatsapp.token'),
], static fn (mixed $valor): bool => ! is_string($valor) || trim($valor) === ''));

if ($faltan !== []) {
    fwrite(STDERR, 'Falta configurar: '.implode(', ', $faltan).".\n");
    exit(1);
}

// El enviador nunca lanza: lo que pasó solo se ve en lo que registra.
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

$celular = Celular::desdeLocalBoliviano($numero);
$digitos = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

(new EnviadorCloudApi(
    $app->make(Http::class),
    (string) config('whatsapp.base_url'),
    (string) config('whatsapp.phone_number_id'),
    (string) config('whatsapp.token'),
    (string) config('whatsapp.plantilla'),
    (string) config('whatsapp.idioma'),
    $log,
))->enviar($celular, $digitos);

echo 'Plantilla '.config('whatsapp.plantilla').' ('.config('whatsapp.idioma').') a '.$celular->e164()
    .' por '.config('whatsapp.base_url')."\n";

if ($log->registros === []) {
    // Aceptado no es entregado: un número sin WhatsApp también da 200, y el
    // fallo llega después, por el webhook de estados.
    echo "Meta aceptó el envío. El código es {$digitos}: tiene que llegar a ese WhatsApp.\n";
    exit(0);
}

foreach ($log->registros as $registro) {
    echo $registro['mensaje'].': '.json_encode($registro['contexto'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
}

exit(1);
