<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\SolicitarDesafio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioErrors;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;

/**
 * Responde lo mismo exista o no la persona: el identificador que devuelve es
 * real en ambos casos, y el error recién aparece en el login.
 */
final readonly class SolicitarDesafioHandler implements RequestHandler
{
    public function __construct(
        private DirectorioDeContactos $directorio,
        private DesafioRepository $desafios,
        private EnviadorDeDesafio $enviador,
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
        $persona = $this->directorio->buscarPorCelular($peticion->celular);

        if ($persona === null) {
            // Ni se guarda ni se envía, pero se devuelve un identificador
            // igual de válido en forma y en tiempo.
            return ResultWithValue::of($id);
        }

        $digitos = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $desafio = DesafioDeIngreso::emitir($id, $peticion->celular, $digitos, $ahora);

        $this->desafios->add($desafio);
        $this->enviador->enviar($peticion->celular, $digitos);

        return ResultWithValue::of($id);
    }
}
