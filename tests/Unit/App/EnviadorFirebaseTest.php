<?php

declare(strict_types=1);

use App\Push\EnviadorFirebase;
use Identidad\Application\Contracts\EstadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\CategoriaDeAviso;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Request;
use Tests\Dobles\LoggerEspia;
use Tests\Soporte\ClaveDePrueba;

const TOKEN_DEL_TELEFONO = 'fcm-token-de-prueba';
const URL_DEL_ENVIO = 'https://fcm.googleapis.com/v1/projects/agropartners-app/messages:send';

/** Una cuenta de servicio de prueba, con la clave RSA de prueba del repo. */
function cuentaDeServicioDePrueba(): string
{
    return (string) json_encode([
        'type' => 'service_account',
        'project_id' => 'agropartners-app',
        'client_email' => 'push@agropartners-app.iam.gserviceaccount.com',
        'private_key' => ClaveDePrueba::pem(),
    ]);
}

function avisoDeFactura(?string $cardCode = 'C-004871'): AvisoParaPush
{
    return new AvisoParaPush(
        'AV-F-1990',
        CategoriaDeAviso::Factura,
        $cardCode,
        'Factura N° 1990 disponible',
        'Ya puedes ver la factura de Sebastian Monasterio.',
        new DateTimeImmutable('2026-11-16T14:00:00Z'),
        '2026-11-16',
    );
}

/** @param array<string, mixed> $respuestaDelEnvio */
function firebaseQueResponde(array $respuestaDelEnvio, int $status, array $cabeceras = []): array
{
    $http = new Http;
    $http->fake([
        'oauth2.googleapis.com/*' => $http->response(['access_token' => 'acceso-de-google', 'expires_in' => 3600, 'token_type' => 'Bearer']),
        'fcm.googleapis.com/*' => $http->response($respuestaDelEnvio, $status, $cabeceras),
    ]);
    $log = new LoggerEspia;
    $enviador = new EnviadorFirebase($http, new Cache(new ArrayStore), 'agropartners-app', cuentaDeServicioDePrueba(), $log);

    return [$enviador, $http, $log];
}

/** @return array<string, mixed> el error de FCM v1, con su código en los detalles */
function errorDeFirebase(int $status, string $estado, string $codigo): array
{
    return ['error' => [
        'code' => $status,
        'message' => "Requested entity was not found. ({$codigo})",
        'status' => $estado,
        'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $codigo]],
    ]];
}

it('un envio aceptado es entregado', function () {
    [$enviador] = firebaseQueResponde(['name' => 'projects/agropartners-app/messages/1'], 200);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->estado)->toBe(EstadoDeEnvio::Entregado);
});

it('cada respuesta de Firebase se clasifica como pide NP20', function (int $status, string $estado, string $codigo, EstadoDeEnvio $esperado) {
    [$enviador] = firebaseQueResponde(errorDeFirebase($status, $estado, $codigo), $status);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->estado)->toBe($esperado);
})->with([
    'UNREGISTERED' => [404, 'NOT_FOUND', 'UNREGISTERED', EstadoDeEnvio::TokenMuerto],
    'INVALID_ARGUMENT' => [400, 'INVALID_ARGUMENT', 'INVALID_ARGUMENT', EstadoDeEnvio::Rechazado],
    'SENDER_ID_MISMATCH' => [403, 'PERMISSION_DENIED', 'SENDER_ID_MISMATCH', EstadoDeEnvio::Rechazado],
    'THIRD_PARTY_AUTH_ERROR' => [401, 'UNAUTHENTICATED', 'THIRD_PARTY_AUTH_ERROR', EstadoDeEnvio::Rechazado],
    'QUOTA_EXCEEDED' => [429, 'RESOURCE_EXHAUSTED', 'QUOTA_EXCEEDED', EstadoDeEnvio::Reintentar],
    'UNAVAILABLE' => [503, 'UNAVAILABLE', 'UNAVAILABLE', EstadoDeEnvio::Reintentar],
    'INTERNAL' => [500, 'INTERNAL', 'INTERNAL', EstadoDeEnvio::Reintentar],
]);

it('un 404 sin UNREGISTERED no borra el token', function () {
    // Un proyecto mal escrito también responde 404: borrar el token ahí dejaría
    // sin avisos a cada persona alcanzada, hasta que vuelva a entrar.
    [$enviador] = firebaseQueResponde(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->estado)->toBe(EstadoDeEnvio::Rechazado);
});

it('la cuota excedida espera al menos un minuto', function () {
    [$enviador] = firebaseQueResponde(errorDeFirebase(429, 'RESOURCE_EXHAUSTED', 'QUOTA_EXCEEDED'), 429, ['Retry-After' => '10']);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->esperaSegundos)->toBe(60);
});

it('un 503 respeta el Retry-After', function () {
    [$enviador] = firebaseQueResponde(errorDeFirebase(503, 'UNAVAILABLE', 'UNAVAILABLE'), 503, ['Retry-After' => '120']);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->esperaSegundos)->toBe(120);
});

it('un 500 sin Retry-After deja la espera al trabajo, que la hace creciente', function () {
    [$enviador] = firebaseQueResponde(errorDeFirebase(500, 'INTERNAL', 'INTERNAL'), 500);

    expect($enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura())->esperaSegundos)->toBeNull();
});

it('pide el token de acceso una sola vez para dos envios', function () {
    [$enviador, $http] = firebaseQueResponde(['name' => 'projects/agropartners-app/messages/1'], 200);

    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura());
    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura());

    expect(count($http->recorded(fn (Request $r): bool => str_contains($r->url(), 'oauth2.googleapis.com'))))->toBe(1)
        ->and(count($http->recorded(fn (Request $r): bool => $r->url() === URL_DEL_ENVIO)))->toBe(2);
});

it('manda el texto del aviso como notificacion y los datos para abrir la pantalla', function () {
    [$enviador, $http] = firebaseQueResponde(['name' => 'projects/agropartners-app/messages/1'], 200);

    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura(cardCode: null));

    $http->assertSent(function (Request $peticion): bool {
        $mensaje = $peticion->data()['message'] ?? [];

        return $peticion->url() === URL_DEL_ENVIO
            && $peticion->hasHeader('Authorization', 'Bearer acceso-de-google')
            && $mensaje['token'] === TOKEN_DEL_TELEFONO
            && $mensaje['notification'] === ['title' => 'Factura N° 1990 disponible', 'body' => 'Ya puedes ver la factura de Sebastian Monasterio.']
            && $mensaje['data'] === ['avisoId' => 'AV-F-1990', 'categoria' => 'factura', 'cardCode' => ''];
    });
});

it('el log trae el motivo de Firebase y nunca el token del telefono', function () {
    [$enviador, $http, $log] = firebaseQueResponde(errorDeFirebase(404, 'NOT_FOUND', 'UNREGISTERED'), 404);

    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura());

    $registrado = (string) json_encode($log->registros);
    $pedidosSinElCampoDelToken = (string) json_encode(array_map(function (array $par): array {
        [$peticion] = $par;
        $datos = $peticion->data();
        unset($datos['message']['token']);

        return ['url' => $peticion->url(), 'cabeceras' => $peticion->headers(), 'datos' => $datos];
    }, $http->recorded()->all()));

    expect($registrado)->toContain('UNREGISTERED')->toContain('404')
        ->and($registrado)->not->toContain(TOKEN_DEL_TELEFONO)
        ->and($pedidosSinElCampoDelToken)->not->toContain(TOKEN_DEL_TELEFONO);
});

it('un 401 borra el token de acceso guardado', function () {
    [$enviador, $http] = firebaseQueResponde(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED', 'message' => 'Request had invalid authentication credentials.']], 401);

    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura());
    $enviador->enviar(TokenPush::desde(TOKEN_DEL_TELEFONO), avisoDeFactura());

    expect(count($http->recorded(fn (Request $r): bool => str_contains($r->url(), 'oauth2.googleapis.com'))))->toBe(2);
});
