<?php

declare(strict_types=1);

use App\Http\Envelope;
use App\Http\ErroresDeRuta;
use App\Http\MapaDeErroresHttp;
use App\Http\Middleware\AutenticarIngesta;
use App\Http\Middleware\AutenticarPorToken;
use App\Http\Middleware\EsquemaRealDetrasDelIngress;
use App\Http\Middleware\ExigirSecretoDelGateway;
use App\Http\Middleware\ForzarJson;
use App\Http\Middleware\Idempotencia;
use App\Http\Middleware\IpRealDetrasDelIngress;
use Core\Results\DomainException;
use Core\Results\FieldError;
use Core\Results\ValidationError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.token' => AutenticarPorToken::class,
            'gateway' => ExigirSecretoDelGateway::class,
            'ingesta.token' => AutenticarIngesta::class,
            'ingesta.idempotencia' => Idempotencia::class,
            'json' => ForzarJson::class,
        ]);

        // Primero de la cadena: todo lo que después mire `ip()` —el filtro de
        // rangos, el freno de intentos, la bitácora— tiene que ver ya la
        // dirección de la persona y no la del ingress. Y toda URL que se arme
        // tiene que salir con el esquema por el que entró la persona.
        $middleware->prepend([IpRealDetrasDelIngress::class, EsquemaRealDetrasDelIngress::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todo es API salvo el back-office, que lo mira una persona.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ! $request->is('admin', 'admin/*') || $request->expectsJson(),
        );

        // Un pedido sin ruta no pasa por ningún middleware de ruta, ForzarJson
        // incluido: sin esto, quien no manda Accept recibe la página HTML de
        // Laravel. Un abort(404) dentro de una ruta que existe no entra acá.
        $exceptions->render(function (NotFoundHttpException $e, Request $peticion): ?JsonResponse {
            if ($peticion->route() !== null || $peticion->is('admin', 'admin/*')) {
                return null;
            }

            $error = ErroresDeRuta::rutaInexistente($peticion->method(), '/'.ltrim($peticion->path(), '/'));

            return new JsonResponse(Envelope::fallo($error), MapaDeErroresHttp::status($error));
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $peticion): ?JsonResponse {
            if ($peticion->is('admin', 'admin/*')) {
                return null;
            }

            $error = ErroresDeRuta::metodoNoPermitido($peticion->method(), '/'.ltrim($peticion->path(), '/'));

            // Allow dice qué métodos sí admite la ruta.
            return new JsonResponse(Envelope::fallo($error), MapaDeErroresHttp::status($error), $e->getHeaders());
        });

        $exceptions->render(function (DomainException $e, Request $peticion): ?JsonResponse {
            if (! $peticion->expectsJson()) {
                return null;
            }

            return new JsonResponse(
                Envelope::fallo($e->getError()),
                MapaDeErroresHttp::status($e->getError()),
            );
        });

        $exceptions->render(function (ValidationException $e, Request $peticion): ?JsonResponse {
            if (! $peticion->expectsJson()) {
                return null;
            }

            // Uno por campo, con el código del contrato. Los mensajes de las
            // reglas de Laravel están en inglés; solo se conservan los de las
            // reglas propias, que ya están escritos para mostrar.
            $errores = [];
            $fallidas = $e->validator->failed();

            foreach ($e->errors() as $campo => $mensajes) {
                $regla = (string) array_key_first($fallidas[$campo] ?? []);

                $errores[] = match (true) {
                    $regla === 'Required', str_starts_with($regla, 'RequiredWith') => new FieldError((string) $campo, 'CAMPO_REQUERIDO', 'Requerido.'),
                    str_contains($regla, '\\') => new FieldError((string) $campo, 'PARAMETRO_INVALIDO', (string) $mensajes[0]),
                    default => new FieldError((string) $campo, 'PARAMETRO_INVALIDO', 'Valor inválido.'),
                };
            }

            return new JsonResponse(Envelope::fallo(new ValidationError(...$errores)), 400);
        });
    })->create();
