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
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioErrors;
use Maestros\Domain\Socios\SocioRepository;

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

        // Una persona inactiva o dada de baja en SAP se trata como inexistente:
        // de esto depende también que no pueda renovar su sesión.
        $persona = $this->contactos->visible($peticion->persona);

        if (! $persona instanceof PersonaDeContacto) {
            return ResultWithValue::failure(
                ContactoErrors::noEncontrado($peticion->persona->value()),
            );
        }

        $socioDeLaPersona = $this->socios->find($persona->codigoDeSocio());

        if (! $socioDeLaPersona instanceof Socio) {
            return ResultWithValue::failure(
                SocioErrors::noEncontrado($persona->codigoDeSocio()->value()),
            );
        }

        $grupo = $socioDeLaPersona->grupo();
        $grupoEconomico = $grupo === null ? null : $this->grupos->find($grupo);

        // Un socio sin su grupo en la réplica no es motivo para negar el
        // contexto: se responde con el nombre vacío.
        $nombreDelGrupo = $grupoEconomico instanceof GrupoEconomico ? $grupoEconomico->nombre() : '';

        // Sin grupo, su alcance es su propio socio, como en ResolutorPorGrupo.
        $sociosDelGrupo = $grupo === null ? [$socioDeLaPersona] : $this->socios->porGrupo($grupo);

        // Una sola consulta para todo el grupo, con la misma definición de
        // «activa» que la lista: el número tiene que coincidir con lo que la
        // persona ve al pedir una visita.
        $cantidades = $this->propiedades->contarPorSocio(array_values(array_map(
            static fn (Socio $s): CodigoDeSocio => $s->codigoDeSocio(),
            $sociosDelGrupo,
        )));

        $socios = array_map(
            static fn (Socio $s): array => [
                'cardCode' => $s->codigoDeSocio()->value(),
                'razonSocial' => $s->razonSocial()->texto(),
                'iniciales' => (string) $s->razonSocial()->iniciales(),
                'cantidadPropiedades' => $cantidades[$s->codigoDeSocio()->value()] ?? 0,
            ],
            $sociosDelGrupo,
        );

        return ResultWithValue::of(new ContextoDeContacto(
            nombre: $persona->nombre(),
            iniciales: (string) $persona->iniciales(),
            celular: $persona->celular()?->paraMostrar(),
            grupoId: $grupo?->value() ?? '',
            grupoNombre: $nombreDelGrupo,
            socios: array_values($socios),
        ));
    }
}
