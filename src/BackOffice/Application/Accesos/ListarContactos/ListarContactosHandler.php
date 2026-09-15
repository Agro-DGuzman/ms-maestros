<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ListarContactos;

use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Maestros\Application\Contactos\ContactoDeBackOffice;
use Maestros\Application\Contactos\ListarContactos\ListarContactos as ListarEnMaestros;
use Maestros\Application\Contactos\PaginaDeContactos;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Junta las dos fuentes que la pantalla necesita separadas: lo que SAP replica
 * y a quién le dimos credencial.
 *
 * Se componen en PHP y no con un JOIN porque viven en esquemas distintos, y
 * ninguna consulta cruza de uno al otro (ADR 0001). Es lo mismo que hace
 * `identidad:conciliar`.
 */
final readonly class ListarContactosHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
        private BovedaDeContrasenas $boveda,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ListarContactos);

        $resultado = $this->mediator->send(new ListarEnMaestros($peticion->criterio));

        if ($resultado->isFailure()) {
            return $resultado;
        }

        assert($resultado instanceof ResultWithValue);
        $pagina = $resultado->value();
        assert($pagina instanceof PaginaDeContactos);

        $conCredencial = array_map(
            static fn (IdDePersona $p): string => $p->value(),
            $this->boveda->personas(),
        );

        return ResultWithValue::of(new PaginaDeAccesos(
            items: array_values(array_map(
                static fn (ContactoDeBackOffice $c): ContactoConAcceso => new ContactoConAcceso(
                    replica: $c,
                    tieneCredencial: in_array($c->idDePersona, $conCredencial, true),
                ),
                $pagina->items,
            )),
            total: $pagina->total,
            pagina: $pagina->pagina,
            totalDePaginas: $pagina->totalDePaginas(),
        ));
    }
}
