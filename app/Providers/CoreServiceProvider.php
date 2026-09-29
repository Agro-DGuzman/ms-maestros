<?php

declare(strict_types=1);

namespace App\Providers;

use App\Persistence\TransactorEloquent;
use BackOffice\Application\Accesos\ConcederAcceso\ConcederAcceso;
use BackOffice\Application\Accesos\ConcederAcceso\ConcederAccesoHandler;
use BackOffice\Application\Accesos\ListarContactos\ListarContactos as ListarAccesos;
use BackOffice\Application\Accesos\ListarContactos\ListarContactosHandler as ListarAccesosHandler;
use BackOffice\Application\Accesos\RevocarAcceso\RevocarAcceso;
use BackOffice\Application\Accesos\RevocarAcceso\RevocarAccesoHandler;
use BackOffice\Application\Bitacora\HistorialDePersona\HistorialDePersona;
use BackOffice\Application\Bitacora\HistorialDePersona\HistorialDePersonaHandler;
use BackOffice\Application\Catalogo\ActualizarEnlaces\ActualizarEnlaces;
use BackOffice\Application\Catalogo\ActualizarEnlaces\ActualizarEnlacesHandler;
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
use Maestros\Application\Contactos\ListarContactos\ListarContactos;
use Maestros\Application\Contactos\ListarContactos\ListarContactosHandler;
use Maestros\Application\Contactos\ListarHabilitadas\ListarHabilitadas;
use Maestros\Application\Contactos\ListarHabilitadas\ListarHabilitadasHandler;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContextoHandler;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProducto;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProductoHandler;
use Maestros\Application\Propiedades\ListarPropiedades\ListarPropiedades;
use Maestros\Application\Propiedades\ListarPropiedades\ListarPropiedadesHandler;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const HANDLERS = [
        BuscarPorCelular::class => BuscarPorCelularHandler::class,
        ActualizarEnlaces::class => ActualizarEnlacesHandler::class,
        CambiarEnlacesDeProducto::class => CambiarEnlacesDeProductoHandler::class,
        ConcederAcceso::class => ConcederAccesoHandler::class,
        HistorialDePersona::class => HistorialDePersonaHandler::class,
        ListarAccesos::class => ListarAccesosHandler::class,
        ListarContactos::class => ListarContactosHandler::class,
        RevocarAcceso::class => RevocarAccesoHandler::class,
        ListarHabilitadas::class => ListarHabilitadasHandler::class,
        ObtenerContexto::class => ObtenerContextoHandler::class,
        ListarPropiedades::class => ListarPropiedadesHandler::class,
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
