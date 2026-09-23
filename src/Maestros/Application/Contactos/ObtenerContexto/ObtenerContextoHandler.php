<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ObtenerContexto;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\ContactoErrors;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioErrors;
use Maestros\Domain\Socios\SocioRepository;

final readonly class ObtenerContextoHandler implements RequestHandler
{
    public function __construct(
        private ContactoRepository $contactos,
        private SocioRepository $socios,
        private GrupoRepository $grupos,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ObtenerContexto);

        $persona = $this->contactos->find($peticion->persona);

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

        $grupo = $socioDeLaPersona->idDeGrupo();
        $grupoEconomico = $this->grupos->find($grupo);

        // Un socio sin su grupo en la réplica no es motivo para negar el
        // contexto: se responde con el nombre vacío.
        $nombreDelGrupo = $grupoEconomico instanceof GrupoEconomico ? $grupoEconomico->nombre() : '';

        $socios = array_map(
            static fn (Socio $s): array => [
                'cardCode' => $s->codigoDeSocio()->value(),
                'razonSocial' => $s->razonSocial()->texto(),
                'iniciales' => (string) $s->razonSocial()->iniciales(),
                // Supuesto S1: el UDT de propiedades no existe todavía en SAP.
                'cantidadPropiedades' => 0,
            ],
            $this->socios->porGrupo($grupo),
        );

        return ResultWithValue::of(new ContextoDeContacto(
            nombre: $persona->nombre(),
            iniciales: (string) $persona->iniciales(),
            celular: $persona->celular()->paraMostrar(),
            grupoId: $grupo->value(),
            grupoNombre: $nombreDelGrupo,
            socios: array_values($socios),
        ));
    }
}
