<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\SolicitarDesafio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\DespachadorDeDesafio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioErrors;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;

/**
 * Hace exactamente el mismo trabajo exista o no la persona: genera dígitos,
 * persiste el desafío y encola el envío. Quién es el dueño del número se
 * resuelve dentro del trabajo encolado, fuera del ciclo de la petición, para
 * que el tiempo de respuesta no diga nada.
 */
final readonly class SolicitarDesafioHandler implements RequestHandler
{
    /**
     * TEMPORAL, se va junto con el eco: los celulares de prueba son los
     * números de EcoDeCodigoDePrueba, inventados y compartidos por todo el
     * equipo de la App. Reciben el código en la respuesta, así que no tienen
     * límite —no los protege de nada— ni WhatsApp, que le llegaría a quien
     * tenga ese número de verdad.
     *
     * @param  list<string>  $celularesDePrueba  en E.164
     */
    public function __construct(
        private DesafioRepository $desafios,
        private DespachadorDeDesafio $despachador,
        private RelojDelSistema $reloj,
        private int $maximoPorHora,
        private array $celularesDePrueba = [],
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof SolicitarDesafio);

        $ahora = $this->reloj->ahora();
        $dePrueba = in_array($peticion->celular->e164(), $this->celularesDePrueba, true);

        // El límite va sobre la EMISIÓN, no solo sobre los intentos: sin esto
        // el atacante compra intentos pidiendo desafíos nuevos.
        if (! $dePrueba && $this->desafios->emitidosDesde($peticion->celular, $ahora->modify('-1 hour')) >= $this->maximoPorHora) {
            return ResultWithValue::failure(DesafioErrors::limiteDeTasa());
        }

        $id = IdDeDesafio::nuevo();
        $digitos = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $this->desafios->add(DesafioDeIngreso::emitir($id, $peticion->celular, $digitos, $ahora));

        if (! $dePrueba) {
            $this->despachador->despachar($peticion->celular, $digitos);
        }

        return ResultWithValue::of($id);
    }
}
