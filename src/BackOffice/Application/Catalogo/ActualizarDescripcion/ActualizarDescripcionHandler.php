<?php

declare(strict_types=1);

namespace BackOffice\Application\Catalogo\ActualizarDescripcion;

use BackOffice\Domain\Catalogo\CambioDeCatalogo;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;
use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\RelojDelSistema;
use Maestros\Application\Productos\CambiarDescripcion\CambiarDescripcionDeProducto;
use Maestros\Application\Productos\DescripcionCambiada;

/**
 * El back-office no sabe guardar productos: despacha el caso de uso de
 * Maestros y asienta lo que cambió, igual que `ActualizarEnlacesHandler`.
 */
final readonly class ActualizarDescripcionHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
        private RegistroDeCambiosDelCatalogo $registro,
        private RelojDelSistema $reloj,
    ) {}

    /** El valor es 1 si la descripción cambió, y 0 si no. */
    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ActualizarDescripcion);

        $resultado = $this->mediator->send(new CambiarDescripcionDeProducto(
            $peticion->idProducto,
            $peticion->descripcion,
        ));

        if ($resultado->isFailure()) {
            return $resultado;
        }

        assert($resultado instanceof ResultWithValue);
        $cambiada = $resultado->value();
        assert($cambiada instanceof DescripcionCambiada);

        // Solo lo que de verdad cambió: una bitácora con cambios que no
        // cambiaron nada mentiría sobre qué texto está publicado.
        if (! $cambiada->cambio()) {
            return ResultWithValue::of(0);
        }

        $this->registro->asentar(CambioDeCatalogo::nuevo(
            operador: $peticion->operador,
            idProducto: $cambiada->idProducto,
            itemCode: $cambiada->itemCode,
            campo: 'descripcion',
            anterior: $cambiada->anterior,
            nuevo: $cambiada->nuevo,
            direccionIp: $peticion->direccionIp,
            ocurrioEl: $this->reloj->ahora(),
        ));

        return ResultWithValue::of(1);
    }
}
