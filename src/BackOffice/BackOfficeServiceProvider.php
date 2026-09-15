<?php

declare(strict_types=1);

namespace BackOffice;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use BackOffice\Infrastructure\Entra\AutenticadorDeDesarrollo;
use BackOffice\Infrastructure\Persistence\EloquentBitacoraRepository;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

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
            return new AutenticadorDeDesarrollo(
                entorno: (string) Config::string('app.env'),
                nombre: Config::string('backoffice.desarrollo.nombre'),
                correo: Config::string('backoffice.desarrollo.correo'),
                oid: Config::string('backoffice.desarrollo.oid'),
                urlDeCallback: url('/admin/callback'),
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom([database_path('migrations/backoffice')]);
    }
}
