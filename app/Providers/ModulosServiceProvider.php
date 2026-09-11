<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\NotificationPublisher;
use Core\Contracts\UnitOfWork;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafioHandler;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Infrastructure\Maestros\DirectorioDeContactosEnProceso;
use Identidad\Infrastructure\Persistence\EloquentDesafioRepository;
use Identidad\Infrastructure\RelojReal;
use Identidad\Infrastructure\Whatsapp\EnviadorPorLog;
use Illuminate\Support\ServiceProvider;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Socios\SocioRepository;
use Maestros\Infrastructure\Importacion\ImportarMaestrosCommand;
use Maestros\Infrastructure\Persistence\EloquentContactoRepository;
use Maestros\Infrastructure\Persistence\EloquentSocioRepository;
use Maestros\Infrastructure\Persistence\EloquentUnitOfWork;
use Maestros\Infrastructure\Persistence\EventoDeLaravelPublisher;

final class ModulosServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SocioRepository::class, EloquentSocioRepository::class);
        $this->app->bind(ContactoRepository::class, EloquentContactoRepository::class);
        $this->app->bind(NotificationPublisher::class, EventoDeLaravelPublisher::class);
        $this->app->bind(UnitOfWork::class, EloquentUnitOfWork::class);

        $this->app->bind(RelojDelSistema::class, RelojReal::class);
        $this->app->bind(EnviadorDeDesafio::class, EnviadorPorLog::class);
        $this->app->bind(DirectorioDeContactos::class, DirectorioDeContactosEnProceso::class);
        $this->app->bind(DesafioRepository::class, EloquentDesafioRepository::class);

        $this->app->when(SolicitarDesafioHandler::class)
            ->needs('$maximoPorHora')
            ->give(fn (): int => (int) config('identidad.desafios_por_hora', 5));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/maestros'),
            database_path('migrations/identidad'),
        ]);

        $this->loadRoutesFrom(base_path('src/Identidad/Presentation/Http/routes.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([ImportarMaestrosCommand::class]);
        }
    }
}
