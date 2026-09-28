<?php

declare(strict_types=1);

namespace Maestros\Application\Productos\CambiarEnlaces;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\DomainException;
use Core\Results\Error;
use Core\Results\FieldError;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Core\Results\ValidationError;
use Maestros\Application\Productos\CambioDeEnlace;
use Maestros\Application\Productos\CampoDeEnlace;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\EnlacesCambiados;
use Maestros\Application\Productos\EnlacesDeProducto;
use Maestros\Application\Productos\ProductoAdministrable;
use Maestros\Domain\Productos\Enlace;

final readonly class CambiarEnlacesDeProductoHandler implements RequestHandler
{
    public function __construct(private CatalogoAdministrable $catalogo) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof CambiarEnlacesDeProducto);

        $producto = $this->catalogo->buscar($peticion->idProducto);

        if (! $producto instanceof ProductoAdministrable) {
            return ResultWithValue::failure(
                Error::notFound('PRODUCTO_NO_ENCONTRADO', 'No existe el producto {id}', $peticion->idProducto),
            );
        }

        // Se validan los cuatro antes de escribir: un error en uno no puede
        // dejar guardados los otros a medias.
        $errores = [];
        $nuevos = new EnlacesDeProducto(
            $this->validar(CampoDeEnlace::Imagen, $peticion->imagen, $errores),
            $this->validar(CampoDeEnlace::FichaTecnica, $peticion->fichaTecnica, $errores),
            $this->validar(CampoDeEnlace::HojaDeSeguridad, $peticion->hojaDeSeguridad, $errores),
            $this->validar(CampoDeEnlace::RegistroSanitario, $peticion->registroSanitario, $errores),
        );

        if ($errores !== []) {
            return ResultWithValue::failure(new ValidationError(...$errores));
        }

        // El anterior sale de la base y no del formulario: si otra persona lo
        // cambió mientras tanto, la bitácora dice lo que de verdad se pisó.
        $cambios = [];

        foreach (CampoDeEnlace::cases() as $campo) {
            $anterior = $producto->enlaces->valor($campo);
            $nuevo = $nuevos->valor($campo);

            if ($anterior !== $nuevo) {
                $cambios[] = new CambioDeEnlace($campo, $anterior, $nuevo);
            }
        }

        if ($cambios !== []) {
            $this->catalogo->guardarEnlaces($producto->id, $nuevos);
        }

        return ResultWithValue::of(new EnlacesCambiados($producto->id, $producto->itemCode, $cambios));
    }

    /**
     * El valor normalizado, o null si el campo quedó vacío. Si es inválido,
     * suma el error del campo y devuelve null: igual no se va a guardar.
     *
     * @param  list<FieldError>  $errores
     */
    private function validar(CampoDeEnlace $campo, ?string $texto, array &$errores): ?string
    {
        try {
            $enlace = $campo->esImagen() ? Enlace::imagen($texto ?? '') : Enlace::documento($texto ?? '');

            return $enlace?->valor();
        } catch (DomainException $e) {
            $errores[] = new FieldError($campo->value, 'ENLACE_INVALIDO', $e->getError()->description);

            return null;
        }
    }
}
