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
    public function __construct(
        private DesafioRepository $desafios,
        private DespachadorDeDesafio $despachador,
        private RelojDelSistema $reloj,
        private int $maximoPorHora,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof SolicitarDesafio);

        $ahora = $this->reloj->ahora();

        // El límite va sobre la EMISIÓN, no solo sobre los intentos: sin esto
        // el atacante compra intentos pidiendo desafíos nuevos.
        if ($this->desafios->emitidosDesde($peticion->celular, $ahora->modify('-1 hour')) >= $this->maximoPorHora) {
            return ResultWithValue::failure(DesafioErrors::limiteDeTasa());
        }

        $id = IdDeDesafio::nuevo();
        $digitos = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        $this->desafios->add(DesafioDeIngreso::emitir($id, $peticion->celular, $digitos, $ahora));
        $this->despachador->despachar($peticion->celular, $digitos);

        return ResultWithValue::of($id);
    }
}
