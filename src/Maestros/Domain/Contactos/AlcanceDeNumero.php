<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;

/**
 * Lo que ve un número: los socios donde es contacto visible, si todos son de
 * un mismo grupo económico. Es la única definición de «entra»: el contexto, el
 * alcance, la habilitación, el back-office y la conciliación la consultan.
 *
 * Un socio sin grupo no suma ni bloquea: no aparece en la App para nadie (D12).
 * Dos grupos no entran, porque la App muestra uno solo.
 */
final readonly class AlcanceDeNumero
{
    /** @param list<CodigoDeSocio> $socios */
    private function __construct(
        private ?MotivoDeNoIngreso $motivo,
        private ?IdDeGrupo $grupo,
        private array $socios,
    ) {}

    /** @param list<array{socio: CodigoDeSocio, grupo: ?IdDeGrupo}> $vinculos */
    public static function desde(array $vinculos): self
    {
        if ($vinculos === []) {
            return new self(MotivoDeNoIngreso::SinContactos, null, []);
        }

        $conGrupo = array_values(array_filter($vinculos, static fn (array $v): bool => $v['grupo'] !== null));

        if ($conGrupo === []) {
            return new self(MotivoDeNoIngreso::SoloSinGrupo, null, []);
        }

        $grupos = array_unique(array_map(static fn (array $v): string => (string) $v['grupo']?->value(), $conGrupo));

        if (count($grupos) > 1) {
            return new self(MotivoDeNoIngreso::VariosGrupos, null, []);
        }

        // Dos contactos del mismo socio son la misma persona dos veces: el
        // socio se ve una sola vez.
        $socios = [];
        foreach ($conGrupo as $vinculo) {
            $socios[$vinculo['socio']->value()] ??= $vinculo['socio'];
        }

        return new self(null, $conGrupo[0]['grupo'], array_values($socios));
    }

    public function entra(): bool
    {
        return $this->motivo === null;
    }

    public function motivo(): ?MotivoDeNoIngreso
    {
        return $this->motivo;
    }

    public function grupo(): ?IdDeGrupo
    {
        return $this->grupo;
    }

    /** @return list<CodigoDeSocio> */
    public function socios(): array
    {
        return $this->socios;
    }

    public function alcanza(CodigoDeSocio $socio): bool
    {
        foreach ($this->socios as $propio) {
            if ($propio->equals($socio)) {
                return true;
            }
        }

        return false;
    }
}
