<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\PublicarSocios;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Eventos\SalidaDeEventos;
use Maestros\Application\Eventos\SocioActualizado;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Socios\SocioRepository;

/**
 * Con el `ocurridoEn` original de cada socio: repetir lo que ms-comercial ya
 * tiene no le cambia nada.
 */
final readonly class PublicarSociosHandler implements RequestHandler
{
    public function __construct(
        private SocioRepository $socios,
        private GrupoRepository $grupos,
        private SalidaDeEventos $salida,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof PublicarSocios);

        // Unos 2186 socios y muchos menos grupos: cada grupo se lee una vez.
        /** @var array<string, GrupoEconomico|null> $grupos */
        $grupos = [];
        $encolados = 0;

        foreach ($this->socios->conLineaYSinBaja() as $socio) {
            $grupo = null;
            $id = $socio->grupo();

            if ($id !== null) {
                if (! array_key_exists($id->value(), $grupos)) {
                    $leido = $this->grupos->find($id);
                    $grupos[$id->value()] = $leido instanceof GrupoEconomico ? $leido : null;
                }

                $grupo = $grupos[$id->value()];
            }

            $evento = SocioActualizado::de($socio, $grupo);

            if ($evento !== null) {
                $this->salida->registrar($evento);
                $encolados++;
            }
        }

        return ResultWithValue::of($encolados);
    }
}
