<?php

declare(strict_types=1);

namespace App\Providers;

use Core\Contracts\Mediator;
use Core\Mediator\Behaviors\AlcanceBehavior;
use Core\Mediator\ContainerMediator;
use Identidad\Application\Auth\CerrarSesion\CerrarSesion;
use Identidad\Application\Auth\CerrarSesion\CerrarSesionHandler;
use Identidad\Application\Auth\IniciarSesion\IniciarSesion;
use Identidad\Application\Auth\IniciarSesion\IniciarSesionHandler;
use Identidad\Application\Auth\RenovarSesion\RenovarSesion;
use Identidad\Application\Auth\RenovarSesion\RenovarSesionHandler;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafio;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafioHandler;
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
        CerrarSesion::class => CerrarSesionHandler::class,
        IniciarSesion::class => IniciarSesionHandler::class,
        RenovarSesion::class => RenovarSesionHandler::class,
        SolicitarDesafio::class => SolicitarDesafioHandler::class,
    ];

    /** @var list<class-string> */
    public const BEHAVIORS = [
        AlcanceBehavior::class,
    ];

    public function register(): void
    {
        $this->app->singleton(Mediator::class, fn ($app): Mediator => new ContainerMediator(
            $app,
            self::HANDLERS,
            self::BEHAVIORS,
        ));
    }
}
