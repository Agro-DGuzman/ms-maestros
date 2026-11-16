<?php

declare(strict_types=1);

namespace Identidad\Application\Push\AnunciarAviso;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\ColaDePush;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\PreferenciasDePushRepository;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Los destinatarios se calculan desde los teléfonos, no desde el socio (NP7):
 * las personas con una sesión abierta con token, y a cada una se le pregunta
 * su contexto. Así el alcance —quién ve qué socio, el grupo económico, las
 * bajas de SAP— queda calculado en un solo lugar, y coincide con lo que
 * ms-comercial le muestra en la bandeja.
 *
 * @return ResultWithValue<int> cuántos trabajos se encolaron
 */
final readonly class AnunciarAvisoHandler implements RequestHandler
{
    public function __construct(
        private SesionRepository $sesiones,
        private DirectorioDeContactos $directorio,
        private PreferenciasDePushRepository $preferencias,
        private ColaDePush $cola,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof AnunciarAviso);

        $aviso = $peticion->aviso;
        $ahora = $this->reloj->ahora();

        if ($aviso->esViejo($ahora)) {
            return ResultWithValue::of(0);
        }

        /** @var array<string, list<SesionDeAplicacion>> $porPersona */
        $porPersona = [];

        foreach ($this->sesiones->abiertasConTokenPush($ahora) as $sesion) {
            $porPersona[$sesion->persona()->value()][] = $sesion;
        }

        // Un teléfono reinstalado puede quedar con el mismo token en dos sesiones:
        // le suena una sola vez. Las claves son hashes, para no tener el token suelto.
        $yaEncolados = [];
        $encolados = 0;

        foreach ($porPersona as $sesiones) {
            if (! $this->recibe($sesiones[0]->persona(), $aviso)) {
                continue;
            }

            foreach ($sesiones as $sesion) {
                $token = $sesion->tokenPush();

                if ($token === null || isset($yaEncolados[hash('sha256', $token->valor())])) {
                    continue;
                }

                $yaEncolados[hash('sha256', $token->valor())] = true;
                $this->cola->encolar($sesion->idDeSesion(), $aviso);
                $encolados++;
            }
        }

        return ResultWithValue::of($encolados);
    }

    /** Ve el socio del aviso —o entra a la App, si es para todos— y tiene la categoría prendida. */
    private function recibe(IdDePersona $persona, AvisoParaPush $aviso): bool
    {
        if (! $this->preferencias->de($persona)->permite($aviso->categoria)) {
            return false;
        }

        $contexto = $this->directorio->contexto($persona);

        if ($contexto === null) {
            return false;
        }

        return $aviso->cardCode === null
            || in_array($aviso->cardCode, array_column($contexto->socios, 'cardCode'), true);
    }
}
