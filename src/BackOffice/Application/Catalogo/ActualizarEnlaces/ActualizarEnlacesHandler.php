<?php

declare(strict_types=1);

namespace BackOffice\Application\Catalogo\ActualizarEnlaces;

use BackOffice\Domain\Catalogo\CambioDeCatalogo;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;
use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\RelojDelSistema;
use Maestros\Application\Productos\CambiarEnlaces\CambiarEnlacesDeProducto;
use Maestros\Application\Productos\EnlacesCambiados;

/**
 * El back-office no sabe guardar productos: despacha el caso de uso de
 * Maestros y asienta lo que cambió, igual que ConcederAcceso con Identidad.
 */
final readonly class ActualizarEnlacesHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
        private RegistroDeCambiosDelCatalogo $registro,
        private RelojDelSistema $reloj,
    ) {}

    /** El valor es la cantidad de campos que cambiaron. */
    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ActualizarEnlaces);

        $resultado = $this->mediator->send(new CambiarEnlacesDeProducto(
            $peticion->idProducto,
            $peticion->imagen,
            $peticion->fichaTecnica,
            $peticion->hojaDeSeguridad,
            $peticion->registroSanitario,
        ));

        // Solo lo que de verdad cambió: una bitácora con intentos fallidos
        // mentiría sobre qué enlace está publicado.
        if ($resultado->isFailure()) {
            return $resultado;
        }

        assert($resultado instanceof ResultWithValue);
        $cambiados = $resultado->value();
        assert($cambiados instanceof EnlacesCambiados);

        $ahora = $this->reloj->ahora();

        foreach ($cambiados->cambios as $cambio) {
            $this->registro->asentar(CambioDeCatalogo::nuevo(
                operador: $peticion->operador,
                idProducto: $cambiados->idProducto,
                itemCode: $cambiados->itemCode,
                campo: $cambio->campo->value,
                anterior: $cambio->anterior,
                nuevo: $cambio->nuevo,
                direccionIp: $peticion->direccionIp,
                ocurrioEl: $ahora,
            ));
        }

        return ResultWithValue::of(count($cambiados->cambios));
    }
}
