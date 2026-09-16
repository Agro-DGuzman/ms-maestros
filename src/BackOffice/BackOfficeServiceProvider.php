<?php

declare(strict_types=1);

namespace BackOffice;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use BackOffice\Infrastructure\Contrasena\AutenticadorDeContrasena;
use BackOffice\Infrastructure\Contrasena\HashDeContrasenaCommand;
use BackOffice\Infrastructure\Contrasena\OperadorConContrasena;
use BackOffice\Infrastructure\Entra\AutenticadorDeDesarrollo;
use BackOffice\Infrastructure\Persistence\EloquentBitacoraRepository;
use BackOffice\Presentation\Http\Middleware\ExigirSesionDeOperador;
use BackOffice\Presentation\Http\Middleware\RestringirPorIp;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * El back-office se registra aparte de `ModulosServiceProvider` porque es un
 * módulo con su propio actor y su propia superficie: mezclarlo haría que las
 * rutas de `/admin/` y las de `/v1/` compartan un solo lugar de registro.
 */
final class BackOfficeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BitacoraRepository::class, EloquentBitacoraRepository::class);

        $this->app->singleton(AutenticadorDeOperador::class, function (): AutenticadorDeOperador {
            $elegido = Config::string('backoffice.autenticador');

            // `AutenticadorEntra` llega cuando TI dé de alta el registro de
            // aplicación y el app role. Hasta entonces, elegir `entra` tiene
            // que fallar al arrancar y no dejar entrar a cualquiera.
            if ($elegido === 'entra') {
                throw new RuntimeException('BACKOFFICE_AUTENTICADOR_ENTRA_NO_IMPLEMENTADO');
            }

            // Lo que corre en el piloto: los dos operadores del equipo entran
            // con contraseña propia, así la bitácora dice quién fue cada uno.
            if ($elegido === 'contrasena') {
                return new AutenticadorDeContrasena(
                    operadores: OperadorConContrasena::listaDesde(
                        Config::string('backoffice.contrasena.operadores'),
                    ),
                    cache: $this->app->make(Cache::class),
                    urlDelFormulario: route('admin.formulario'),
                );
            }

            return new AutenticadorDeDesarrollo(
                entorno: $this->app->environment(),
                nombre: Config::string('backoffice.desarrollo.nombre'),
                correo: Config::string('backoffice.desarrollo.correo'),
                oid: Config::string('backoffice.desarrollo.oid'),
                urlDeCallback: route('admin.callback'),
            );
        });

        $this->app->singleton(RestringirPorIp::class, fn (): RestringirPorIp => new RestringirPorIp(
            rangos: array_values(array_filter(
                Config::array('backoffice.rangos_ip'),
                static fn (mixed $r): bool => is_string($r) && $r !== '',
            )),
            entorno: $this->app->environment(),
        ));
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('backoffice.ip', RestringirPorIp::class);
        $router->aliasMiddleware('backoffice.sesion', ExigirSesionDeOperador::class);

        $this->loadMigrationsFrom([database_path('migrations/backoffice')]);
        $this->loadRoutesFrom(__DIR__.'/Presentation/Http/routes.php');
        $this->loadViewsFrom(__DIR__.'/Presentation/Views', 'backoffice');

        if ($this->app->runningInConsole()) {
            $this->commands([HashDeContrasenaCommand::class]);
        }
    }
}
