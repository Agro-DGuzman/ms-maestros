<?php

declare(strict_types=1);

use App\Http\Envelope;
use App\Http\MapaDeErroresHttp;
use App\Http\Middleware\AutenticarPorToken;
use App\Http\Middleware\EsquemaRealDetrasDelIngress;
use App\Http\Middleware\IpRealDetrasDelIngress;
use Core\Results\DomainException;
use Core\Results\Error;
use Core\Results\ValidationError;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['auth.token' => AutenticarPorToken::class]);

        // Primero de la cadena: todo lo que después mire `ip()` —el filtro de
        // rangos, el freno de intentos, la bitácora— tiene que ver ya la
        // dirección de la persona y no la del ingress. Y toda URL que se arme
        // tiene que salir con el esquema por el que entró la persona.
        $middleware->prepend([IpRealDetrasDelIngress::class, EsquemaRealDetrasDelIngress::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

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

            $errores = [];

            foreach ($e->errors() as $campo => $mensajes) {
                foreach ($mensajes as $mensaje) {
                    $errores[] = Error::validation(
                        mb_strtoupper((string) $campo).'_INVALIDO',
                        (string) $mensaje,
                    );
                }
            }

            return new JsonResponse(Envelope::fallo(new ValidationError(...$errores)), 422);
        });
    })->create();
