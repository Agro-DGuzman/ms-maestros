<?php

declare(strict_types=1);

namespace Maestros\Application\Productos\CambiarDescripcion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Error;
use Core\Results\FieldError;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Core\Results\ValidationError;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\DescripcionCambiada;
use Maestros\Application\Productos\ProductoAdministrable;

/**
 * La descripción es lo único del texto del producto que no viene de SAP (P5):
 * la carga el área desde el back-office y la ingesta nunca la escribe.
 */
final readonly class CambiarDescripcionDeProductoHandler implements RequestHandler
{
    /** El largo de `valor_anterior` y `valor_nuevo` en la bitácora, que así no cambia. */
    private const MAXIMO = 1000;

    public function __construct(private CatalogoAdministrable $catalogo) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof CambiarDescripcionDeProducto);

        $nueva = $peticion->descripcion === null ? null : trim($peticion->descripcion);
        $nueva = $nueva === '' ? null : $nueva;

        if ($nueva !== null && mb_strlen($nueva) > self::MAXIMO) {
            return ResultWithValue::failure(new ValidationError(new FieldError(
                'descripcion',
                'DESCRIPCION_DEMASIADO_LARGA',
                'La descripción admite hasta 1000 caracteres.',
            )));
        }

        $producto = $this->catalogo->buscar($peticion->idProducto);

        if (! $producto instanceof ProductoAdministrable) {
            return ResultWithValue::failure(
                Error::notFound('PRODUCTO_NO_ENCONTRADO', 'No existe el producto {id}', $peticion->idProducto),
            );
        }

        // El anterior sale de la base y no del formulario: si otra persona lo
        // cambió mientras tanto, la bitácora dice lo que de verdad se pisó.
        $cambiada = new DescripcionCambiada(
            $producto->id,
            $producto->itemCode,
            $producto->descripcion,
            $nueva,
        );

        if ($cambiada->cambio()) {
            $this->catalogo->guardarDescripcion($producto->id, $nueva);
        }

        return ResultWithValue::of($cambiada);
    }
}
