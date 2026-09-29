<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Application\Catalogo\ActualizarEnlaces\ActualizarEnlaces;
use BackOffice\Domain\Catalogo\RegistroDeCambiosDelCatalogo;
use BackOffice\Domain\Operadores\Operador;
use Closure;
use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Core\Results\ResultWithValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maestros\Application\Productos\CampoDeEnlace;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\FiltroDeEnlaces;
use Maestros\Application\Productos\ProductoAdministrable;
use Maestros\Domain\Productos\Enlace;
use RuntimeException;

/**
 * Los enlaces del catálogo que no vienen de SAP. Lo demás del producto se
 * muestra para reconocerlo, pero no se edita acá.
 */
final readonly class ProductosController
{
    public function __construct(
        private Mediator $mediator,
        private CatalogoAdministrable $catalogo,
        private RegistroDeCambiosDelCatalogo $registro,
    ) {}

    public function index(Request $pedido): View
    {
        $texto = $pedido->query('q');
        $filtro = $pedido->query('filtro');
        $filtro = FiltroDeEnlaces::tryFrom(is_string($filtro) ? $filtro : '') ?? FiltroDeEnlaces::Todos;
        $texto = is_string($texto) ? $texto : null;

        return view('backoffice::productos', [
            'productos' => $this->catalogo->listar($texto, $filtro),
            'texto' => $texto,
            'filtro' => $filtro,
        ]);
    }

    public function mostrar(int $id): View
    {
        return view('backoffice::producto', [
            'producto' => $this->productoOAbortar($id),
            'campos' => CampoDeEnlace::cases(),
            'historial' => $this->registro->historialDe($id),
        ]);
    }

    public function guardar(Request $pedido, int $id): RedirectResponse
    {
        $producto = $this->productoOAbortar($id);

        // La misma regla que valida Maestros, acá para que el error vuelva
        // pegado al campo y con lo que la persona tecleó.
        $reglas = [];

        // `present`: vaciar un campo quita el enlace, pero que el campo no
        // venga no. Un script o un formulario al que le falte uno no puede
        // despublicar lo que ya estaba.
        foreach (CampoDeEnlace::cases() as $campo) {
            $reglas[$campo->value] = ['present', 'nullable', 'string', self::enlaceValido($campo)];
        }

        $datos = $pedido->validate($reglas);

        $resultado = $this->mediator->send(new ActualizarEnlaces(
            idProducto: $producto->id,
            imagen: self::texto($datos, CampoDeEnlace::Imagen),
            fichaTecnica: self::texto($datos, CampoDeEnlace::FichaTecnica),
            hojaDeSeguridad: self::texto($datos, CampoDeEnlace::HojaDeSeguridad),
            registroSanitario: self::texto($datos, CampoDeEnlace::RegistroSanitario),
            operador: $this->operador(),
            direccionIp: (string) $pedido->ip(),
        ));

        $destino = redirect()->route('admin.producto', $producto->id);

        if ($resultado->isFailure()) {
            return $destino->with('error', $resultado->error->description);
        }

        assert($resultado instanceof ResultWithValue);
        $cantidad = $resultado->value();
        assert(is_int($cantidad));

        return $destino->with('aviso', match (true) {
            $cantidad === 0 => 'No había cambios.',
            $cantidad === 1 => 'Se guardó 1 cambio.',
            default => "Se guardaron {$cantidad} cambios.",
        });
    }

    private function productoOAbortar(int $id): ProductoAdministrable
    {
        $producto = $this->catalogo->buscar($id);

        if ($producto === null) {
            abort(404);
        }

        return $producto;
    }

    private static function enlaceValido(CampoDeEnlace $campo): Closure
    {
        return static function (string $atributo, mixed $valor, Closure $fallar) use ($campo): void {
            $texto = is_string($valor) ? $valor : '';

            try {
                $campo->esImagen() ? Enlace::imagen($texto) : Enlace::documento($texto);
            } catch (DomainException $e) {
                $fallar($e->getError()->description);
            }
        };
    }

    /**
     * La validación ya exigió los cuatro campos; acá un null es un campo que
     * vino vacío, y vacío quita el enlace.
     *
     * @param  array<string, mixed>  $datos
     */
    private static function texto(array $datos, CampoDeEnlace $campo): ?string
    {
        $valor = $datos[$campo->value] ?? null;

        return is_string($valor) ? $valor : null;
    }

    private function operador(): Operador
    {
        return SesionDeOperador::actual()
            ?? throw new RuntimeException('La ruta no pasó por el middleware backoffice.sesion');
    }
}
