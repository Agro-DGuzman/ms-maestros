<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\NotificationPublisher;
use Core\Contracts\UnitOfWork;
use Illuminate\Support\ServiceProvider;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Socios\SocioRepository;
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
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/maestros'),
            database_path('migrations/identidad'),
        ]);
    }
}
