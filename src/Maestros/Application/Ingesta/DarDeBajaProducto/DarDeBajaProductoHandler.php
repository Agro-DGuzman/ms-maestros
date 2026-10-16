<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\DarDeBajaProducto;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Maestros\Application\Ingesta\EstadoDeProductoReplicado;
use Maestros\Application\Ingesta\IngestaErrors;
use Maestros\Application\Ingesta\ReplicaDeProductos;

/**
 * Baja lógica: la fila queda, con su ficha, su descripción y sus enlaces, para
 * que el artículo vuelva entero si SAP lo reactiva. La vigencia pasa a ser el
 * momento de la baja, así un envío leído en SAP antes no lo resucita.
 */
final readonly class DarDeBajaProductoHandler implements RequestHandler
{
    public function __construct(private ReplicaDeProductos $productos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof DarDeBajaProducto);

        $estado = $this->productos->estado($peticion->itemCode);

        // El Sincronizador toma este 404 como éxito: el producto ya no estaba.
        if (! $estado instanceof EstadoDeProductoReplicado || $estado->dadoDeBaja) {
            return Result::failure(IngestaErrors::productoNoEncontrado($peticion->itemCode));
        }

        $this->productos->darDeBaja($peticion->itemCode, $peticion->momento);

        return Result::success();
    }
}
