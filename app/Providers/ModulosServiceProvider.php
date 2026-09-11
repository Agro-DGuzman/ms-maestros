<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\NotificationPublisher;
use Core\Contracts\UnitOfWork;
use Identidad\Application\Auth\IniciarSesion\IniciarSesionHandler;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafioHandler;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Contracts\VerificadorDeToken;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Sesiones\SesionRepository;
use Identidad\Infrastructure\Keycloak\KeycloakEmisorDeToken;
use Identidad\Infrastructure\Keycloak\VerificadorJwks;
use Identidad\Infrastructure\Maestros\DirectorioDeContactosEnProceso;
use Identidad\Infrastructure\Persistence\BovedaCifrada;
use Identidad\Infrastructure\Persistence\EloquentDesafioRepository;
use Identidad\Infrastructure\Persistence\EloquentSesionRepository;
use Identidad\Infrastructure\RelojReal;
use Identidad\Infrastructure\Whatsapp\EnviadorPorLog;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
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
        $this->app->bind(SesionRepository::class, EloquentSesionRepository::class);

        $this->app->bind(BovedaDeContrasenas::class, BovedaCifrada::class);

        $this->app->singleton(VerificadorDeToken::class, fn ($app): VerificadorDeToken => new VerificadorJwks(
            $app->make(Http::class),
            $app->make(Cache::class),
            (string) config('keycloak.base_url'),
            (string) config('keycloak.realm'),
        ));

        $this->app->singleton(EmisorDeToken::class, fn ($app): EmisorDeToken => new KeycloakEmisorDeToken(
            $app->make(Http::class),
            $app->make(LoggerInterface::class),
            (string) config('keycloak.base_url'),
            (string) config('keycloak.realm'),
            (string) config('keycloak.client_id'),
            (string) config('keycloak.client_secret'),
            (int) config('keycloak.timeout'),
        ));

        $this->app->when(SolicitarDesafioHandler::class)
            ->needs('$maximoPorHora')
            ->give(fn (): int => (int) config('identidad.desafios_por_hora', 5));

        $this->app->when(IniciarSesionHandler::class)
            ->needs('$diasDeSesion')
            ->give(fn (): int => (int) config('identidad.dias_de_sesion', 30));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom([
            database_path('migrations/maestros'),
            database_path('migrations/identidad'),
        ]);

        $this->loadRoutesFrom(base_path('src/Identidad/Presentation/Http/routes.php'));
        $this->loadRoutesFrom(base_path('src/Maestros/Presentation/Http/routes.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([ImportarMaestrosCommand::class]);
        }
    }
}
