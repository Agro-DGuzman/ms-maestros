<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ReplicarSocio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\DomainException;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use DateTimeImmutable;
use Maestros\Application\Ingesta\IngestaErrors;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Maestros\Application\Ingesta\SocioIngresado;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioErrors;
use Maestros\Domain\Socios\SocioRepository;

/**
 * Escribe lo que SAP confirmó sobre un socio: grupo, socio y contactos, en ese
 * orden y en una transacción (D14).
 *
 * Todo lo que puede fallar se decide antes de la primera escritura: un
 * `Result` fallido confirma la transacción, y un grupo escrito antes de una
 * validación fallida quedaría huérfano.
 */
final readonly class ReplicarSocioHandler implements RequestHandler
{
    public function __construct(
        private SocioRepository $socios,
        private GrupoRepository $grupos,
        private ContactoRepository $contactos,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ReplicarSocio);

        $cuerpo = $peticion->socio;
        $reemplazar = $peticion->operacion === OperacionDeIngesta::Reemplazar;

        if ($reemplazar && $peticion->cardCodeDeLaRuta !== $cuerpo->cardCode) {
            return ResultWithValue::failure(IngestaErrors::cardCodeNoCoincide());
        }

        $codigo = CodigoDeSocio::desde($cuerpo->cardCode);
        $existente = $this->socios->find($codigo);
        $vigente = $existente instanceof Socio && $existente->dadoDeBajaEl() === null;

        if (! $reemplazar && $vigente) {
            return ResultWithValue::failure(IngestaErrors::socioYaExiste($cuerpo->cardCode));
        }

        if ($reemplazar && ! $vigente) {
            return ResultWithValue::failure(SocioErrors::noEncontrado($cuerpo->cardCode));
        }

        // Solo los clientes son socios: un proveedor o un lead no se conserva,
        // y un socio que deja de ser cliente se da de baja.
        if ($cuerpo->tipoSap !== 'C') {
            if (! $existente instanceof Socio || ! $vigente) {
                return ResultWithValue::of(ResultadoDeIngesta::NoConservado);
            }

            $this->socios->save($existente->dadoDeBaja($peticion->momento));
            $this->contactos->darDeBajaLosQueNoVinieron($codigo, [], $peticion->momento);

            return ResultWithValue::of(ResultadoDeIngesta::DadoDeBaja);
        }

        // Una réplica nunca retrocede, tampoco detrás de una baja: la baja fijó
        // su vigencia en el momento en que se hizo.
        if ($existente instanceof Socio && ! self::esMasNuevo($cuerpo->vigenteDesde, $existente->vigenteDesde())) {
            return ResultWithValue::of(ResultadoDeIngesta::IgnoradoPorViejo);
        }

        // Todo lo que puede lanzar, armado antes de escribir.
        $razonSocial = RazonSocial::desde($cuerpo->razonSocial);
        $grupo = $cuerpo->grupo === null ? null : IdDeGrupo::desde($cuerpo->grupo->codigo);
        $ids = array_map(static fn ($c): IdDePersona => IdDePersona::deContactoSap($c->codigo), $cuerpo->contactos);

        if ($cuerpo->grupo !== null && $grupo !== null) {
            $this->escribirGrupo($grupo, $cuerpo);
        }

        $this->socios->save(Socio::replica(
            $codigo,
            $razonSocial,
            $grupo,
            $cuerpo->vigenteDesde,
            activo: $cuerpo->activo,
            origenEsquema: $cuerpo->origenEsquema,
            origenEventoId: $cuerpo->origenEventoId,
        ));

        foreach ($cuerpo->contactos as $i => $contacto) {
            $id = $ids[$i];
            $anterior = $this->contactos->find($id);

            $this->contactos->save(PersonaDeContacto::replica(
                $id,
                $codigo,
                $contacto->nombre,
                self::celular($contacto->celular),
                // La habilitación la decide el back-office, nunca SAP.
                $anterior instanceof PersonaDeContacto ? $anterior->habilitadaEl() : null,
                $cuerpo->vigenteDesde,
                activa: $contacto->activo,
            ));
        }

        $this->contactos->darDeBajaLosQueNoVinieron($codigo, array_values($ids), $peticion->momento);

        return ResultWithValue::of(ResultadoDeIngesta::Aplicado);
    }

    /**
     * El grupo tiene su propia vigencia (D14): dos socios del mismo grupo se
     * leen en momentos distintos, y un nombre viejo no pisa uno nuevo.
     */
    private function escribirGrupo(IdDeGrupo $id, SocioIngresado $cuerpo): void
    {
        assert($cuerpo->grupo !== null);

        $guardado = $this->grupos->find($id);

        if ($guardado instanceof GrupoEconomico && ! self::esMasNuevo($cuerpo->vigenteDesde, $guardado->vigenteDesde())) {
            return;
        }

        $this->grupos->save(GrupoEconomico::replica($id, $cuerpo->grupo->nombre, $cuerpo->vigenteDesde, $cuerpo->grupo->segmento));
    }

    /**
     * Lo guardado está truncado al segundo: el cuerpo se trunca igual, y
     * empatar es no reemplazar. Sin eso, un envío viejo dentro del mismo
     * segundo parecería más nuevo.
     */
    private static function esMasNuevo(DateTimeImmutable $cuerpo, DateTimeImmutable $guardado): bool
    {
        return $cuerpo->getTimestamp() > $guardado->getTimestamp();
    }

    /** Un celular que no es un móvil boliviano no rechaza al socio: el contacto queda sin celular. */
    private static function celular(?string $celular): ?Celular
    {
        if ($celular === null || trim($celular) === '') {
            return null;
        }

        try {
            return Celular::desdeLocalBoliviano($celular);
        } catch (DomainException) {
            return null;
        }
    }
}
