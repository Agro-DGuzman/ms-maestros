<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ProcesarEvento;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Ingesta\RegistroDeEventos;
use Maestros\Application\Ingesta\ReplicaDePropiedades;

/**
 * Maestros atiende las propiedades; lo demás que le llega lo delega en
 * OtrosEventos, sin saber quién lo atiende. Un tipo que nadie atiende lanza:
 * no se completa y termina en mensajes muertos, en vez de registrarse como
 * procesado sin haberse aplicado.
 */
final readonly class ProcesarEventoHandler implements RequestHandler
{
    public function __construct(
        private RegistroDeEventos $registro,
        private ReplicaDePropiedades $propiedades,
        private OtrosEventos $otros,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ProcesarEvento);

        $sobre = $peticion->sobre;

        if ($this->registro->yaProcesado($sobre->eventoId)) {
            return ResultWithValue::of(ResultadoDelEvento::Duplicado);
        }

        if ($sobre->tipo === TraductorDePropiedad::TIPO) {
            $resultado = $this->propiedades->guardarSiEsMasNueva(TraductorDePropiedad::traducir($sobre))
                ? ResultadoDelEvento::Aplicado
                : ResultadoDelEvento::IgnoradoPorViejo;
        } elseif ($this->otros->atiende($sobre->tipo)) {
            $resultado = $this->otros->atender($sobre);
        } else {
            throw EventoInvalido::sinTraductor($sobre->tipo);
        }

        $this->registro->registrar($sobre->eventoId, $sobre->tipo, $resultado);

        return ResultWithValue::of($resultado);
    }
}
