<?php

declare(strict_types=1);

namespace App\Providers;

use App\Persistence\TransactorEloquent;
use Core\Contracts\Mediator;
use Core\Contracts\Transactor;
use Core\Mediator\Behaviors\AlcanceBehavior;
use Core\Mediator\Behaviors\TransaccionBehavior;
use Core\Mediator\ContainerMediator;
use Identidad\Application\Auth\CerrarSesion\CerrarSesion;
use Identidad\Application\Auth\CerrarSesion\CerrarSesionHandler;
use Identidad\Application\Auth\IniciarSesion\IniciarSesion;
use Identidad\Application\Auth\IniciarSesion\IniciarSesionHandler;
use Identidad\Application\Auth\RenovarSesion\RenovarSesion;
use Identidad\Application\Auth\RenovarSesion\RenovarSesionHandler;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafio;
use Identidad\Application\Auth\SolicitarDesafio\SolicitarDesafioHandler;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersonaHandler;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersonaHandler;
use Illuminate\Support\ServiceProvider;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelularHandler;
use Maestros\Application\Contactos\ListarHabilitadas\ListarHabilitadas;
use Maestros\Application\Contactos\ListarHabilitadas\ListarHabilitadasHandler;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContextoHandler;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const HANDLERS = [
        BuscarPorCelular::class => BuscarPorCelularHandler::class,
        ListarHabilitadas::class => ListarHabilitadasHandler::class,
        ObtenerContexto::class => ObtenerContextoHandler::class,
        CerrarSesion::class => CerrarSesionHandler::class,
        DeshabilitarPersona::class => DeshabilitarPersonaHandler::class,
        HabilitarPersona::class => HabilitarPersonaHandler::class,
        IniciarSesion::class => IniciarSesionHandler::class,
        RenovarSesion::class => RenovarSesionHandler::class,
        SolicitarDesafio::class => SolicitarDesafioHandler::class,
    ];

    /**
     * El orden importa: la transacción envuelve al alcance, así que un rechazo
     * por alcance deshace cualquier escritura que un behavior posterior
     * hubiera hecho.
     *
     * @var list<class-string>
     */
    public const BEHAVIORS = [
        TransaccionBehavior::class,
        AlcanceBehavior::class,
    ];

    public function register(): void
    {
        $this->app->bind(Transactor::class, TransactorEloquent::class);

        $this->app->singleton(Mediator::class, fn ($app): Mediator => new ContainerMediator(
            $app,
            self::HANDLERS,
            self::BEHAVIORS,
        ));
    }
}
