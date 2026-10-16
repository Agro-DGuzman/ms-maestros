<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\ReplicarProducto;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Ingesta\EstadoDeProductoReplicado;
use Maestros\Application\Ingesta\IngestaErrors;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ReplicaDeProductos;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Maestros\Application\Ingesta\Vigencia;

/**
 * Escribe la ficha que SAP confirmó de un artículo, sin tocar la descripción
 * ni los enlaces que carga el back-office (P5).
 *
 * Todo lo que puede fallar se decide antes de la primera escritura: un
 * `Result` fallido confirma la transacción, y una categoría creada antes de
 * una validación fallida quedaría colgada.
 */
final readonly class ReplicarProductoHandler implements RequestHandler
{
    public function __construct(private ReplicaDeProductos $productos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ReplicarProducto);

        $cuerpo = $peticion->producto;
        $reemplazar = $peticion->operacion === OperacionDeIngesta::Reemplazar;

        if ($reemplazar && $peticion->itemCodeDeLaRuta !== $cuerpo->itemCode) {
            return ResultWithValue::failure(IngestaErrors::itemCodeNoCoincide());
        }

        $estado = $this->productos->estado($cuerpo->itemCode);
        $vigente = $estado instanceof EstadoDeProductoReplicado && ! $estado->dadoDeBaja;

        if (! $reemplazar && $vigente) {
            return ResultWithValue::failure(IngestaErrors::productoYaExiste($cuerpo->itemCode));
        }

        if ($reemplazar && ! $vigente) {
            return ResultWithValue::failure(IngestaErrors::productoNoEncontrado($cuerpo->itemCode));
        }

        // Una réplica nunca retrocede, tampoco detrás de una baja: la baja fijó
        // su vigencia en el momento en que se hizo, así que un envío leído en
        // SAP antes no reactiva el producto. Uno de la web no tiene vigencia y
        // acepta cualquier cuerpo.
        if ($estado?->vigenteDesde !== null && ! Vigencia::esMasNuevo($cuerpo->vigenteDesde, $estado->vigenteDesde)) {
            return ResultWithValue::of(ResultadoDeIngesta::IgnoradoPorViejo);
        }

        $this->productos->replicar($cuerpo);

        return ResultWithValue::of(ResultadoDeIngesta::Aplicado);
    }
}
