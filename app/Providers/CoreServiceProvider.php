<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\Mediator;
use Core\Mediator\ContainerMediator;
use Illuminate\Support\ServiceProvider;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelularHandler;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContextoHandler;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const HANDLERS = [
        BuscarPorCelular::class => BuscarPorCelularHandler::class,
        ObtenerContexto::class => ObtenerContextoHandler::class,
    ];

    /** @var list<class-string> */
    public const BEHAVIORS = [];

    public function register(): void
    {
        $this->app->singleton(Mediator::class, fn ($app): Mediator => new ContainerMediator(
            $app,
            self::HANDLERS,
            self::BEHAVIORS,
        ));
    }
}
