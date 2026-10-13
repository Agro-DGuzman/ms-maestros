<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ObtenerContexto;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Propiedades\PropiedadesDeSocios;
use Maestros\Domain\Contactos\ContactoErrors;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

/**
 * Lo que ve la persona que es este número: los socios donde es contacto
 * visible, y el nombre de su grupo, que es el que la App le muestra.
 */
final readonly class ObtenerContextoHandler implements RequestHandler
{
    public function __construct(
        private ContactoRepository $contactos,
        private SocioRepository $socios,
        private GrupoRepository $grupos,
        private PropiedadesDeSocios $propiedades,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ObtenerContexto);

        $celular = $peticion->persona->celular();
        $alcance = $celular === null ? null : $this->contactos->alcanceDeNumero($celular);
        $grupo = $alcance?->grupo();

        // Un número que no entra se trata como inexistente: de esto dependen
        // también el ingreso y que no pueda renovar su sesión. Un id que no
        // sale de un número es de un token anterior al cambio.
        if ($celular === null || $alcance === null || $grupo === null) {
            return ResultWithValue::failure(ContactoErrors::noEncontrado($peticion->persona->value()));
        }

        // La identidad del grupo es su código: si la réplica todavía no trajo
        // la fila, el nombre sale vacío pero el contexto no se niega.
        $grupoEconomico = $this->grupos->find($grupo);
        $nombreDelGrupo = $grupoEconomico instanceof GrupoEconomico ? $grupoEconomico->nombre() : '';

        $socios = array_values(array_filter(array_map(
            fn (CodigoDeSocio $codigo): ?Socio => ($socio = $this->socios->find($codigo)) instanceof Socio ? $socio : null,
            $alcance->socios(),
        )));

        // Una sola consulta para todos, con la misma definición de «activa»
        // que la lista: el número tiene que coincidir con lo que la persona
        // ve al pedir una visita.
        $cantidades = $this->propiedades->contarPorSocio($alcance->socios());

        return ResultWithValue::of(new ContextoDeContacto(
            nombre: $nombreDelGrupo,
            iniciales: $nombreDelGrupo === '' ? '' : (string) RazonSocial::desde($nombreDelGrupo)->iniciales(),
            celular: $celular->paraMostrar(),
            grupoId: $grupo->value(),
            grupoNombre: $nombreDelGrupo,
            socios: array_map(
                static fn (Socio $s): array => [
                    'cardCode' => $s->codigoDeSocio()->value(),
                    'razonSocial' => $s->razonSocial()->texto(),
                    'iniciales' => (string) $s->razonSocial()->iniciales(),
                    'cantidadPropiedades' => $cantidades[$s->codigoDeSocio()->value()] ?? 0,
                ],
                $socios,
            ),
        ));
    }
}
