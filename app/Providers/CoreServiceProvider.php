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
use BackOffice\Application\Catalogo\ActualizarDescripcion\ActualizarDescripcion;
use BackOffice\Application\Catalogo\ActualizarDescripcion\ActualizarDescripcionHandler;
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
use Maestros\Application\Contactos\EvaluarNumero\EvaluarNumero;
use Maestros\Application\Contactos\EvaluarNumero\EvaluarNumeroHandler;
use Maestros\Application\Contactos\ListarContactos\ListarContactos;
use Maestros\Application\Contactos\ListarContactos\ListarContactosHandler;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContextoHandler;
use Maestros\Application\Ingesta\DarDeBajaProducto\DarDeBajaProducto;
use Maestros\Application\Ingesta\DarDeBajaProducto\DarDeBajaProductoHandler;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocio;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocioHandler;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEventoHandler;
use Maestros\Application\Ingesta\PublicarSocios\PublicarSocios;
use Maestros\Application\Ingesta\PublicarSocios\PublicarSociosHandler;
use Maestros\Application\Ingesta\ReplicarProducto\ReplicarProducto;
use Maestros\Application\Ingesta\ReplicarProducto\ReplicarProductoHandler;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocioHandler;
use Maestros\Application\Productos\CambiarDescripcion\CambiarDescripcionDeProducto;
use Maestros\Application\Productos\CambiarDescripcion\CambiarDescripcionDeProductoHandler;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProducto;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProductoHandler;
use Maestros\Application\Propiedades\ListarPropiedades\ListarPropiedades;
use Maestros\Application\Propiedades\ListarPropiedades\ListarPropiedadesHandler;

final class CoreServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public const HANDLERS = [
        EvaluarNumero::class => EvaluarNumeroHandler::class,
        ActualizarDescripcion::class => ActualizarDescripcionHandler::class,
        ActualizarEnlaces::class => ActualizarEnlacesHandler::class,
        CambiarDescripcionDeProducto::class => CambiarDescripcionDeProductoHandler::class,
        CambiarEnlacesDeProducto::class => CambiarEnlacesDeProductoHandler::class,
        ConcederAcceso::class => ConcederAccesoHandler::class,
        HistorialDePersona::class => HistorialDePersonaHandler::class,
        ListarAccesos::class => ListarAccesosHandler::class,
        ListarContactos::class => ListarContactosHandler::class,
        RevocarAcceso::class => RevocarAccesoHandler::class,
        ObtenerContexto::class => ObtenerContextoHandler::class,
        ListarPropiedades::class => ListarPropiedadesHandler::class,
        ReplicarSocio::class => ReplicarSocioHandler::class,
        DarDeBajaSocio::class => DarDeBajaSocioHandler::class,
        PublicarSocios::class => PublicarSociosHandler::class,
        ProcesarEvento::class => ProcesarEventoHandler::class,
        ReplicarProducto::class => ReplicarProductoHandler::class,
        DarDeBajaProducto::class => DarDeBajaProductoHandler::class,
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
