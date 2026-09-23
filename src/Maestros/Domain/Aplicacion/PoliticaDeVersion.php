<?php

declare(strict_types=1);

namespace Maestros\Domain\Aplicacion;

/**
 * Qué versiones de la App se dejan pasar en una plataforma. Es un interruptor
 * de emergencia: permite retirar una versión con un fallo sin publicar otra.
 *
 * Sin versiones configuradas deja pasar todo. Fallar cerrado por una variable
 * que falta dejaría afuera a todos los socios a la vez.
 */
final readonly class PoliticaDeVersion
{
    public function __construct(
        public ?Version $minima,
        public ?Version $recomendada,
        public ?string $urlTienda,
        private AvisoDeVersion $alExigir,
        private AvisoDeVersion $alSugerir,
    ) {}

    public function evaluar(Version $instalada): EstadoDeVersion
    {
        if ($this->minima !== null && $instalada->menorQue($this->minima)) {
            return EstadoDeVersion::ActualizacionObligatoria;
        }

        if ($this->recomendada !== null && $instalada->menorQue($this->recomendada)) {
            return EstadoDeVersion::ActualizacionSugerida;
        }

        return EstadoDeVersion::Vigente;
    }

    public function aviso(EstadoDeVersion $estado): ?AvisoDeVersion
    {
        return match ($estado) {
            EstadoDeVersion::ActualizacionObligatoria => $this->alExigir,
            EstadoDeVersion::ActualizacionSugerida => $this->alSugerir,
            EstadoDeVersion::Vigente => null,
        };
    }
}
