<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use Core\Results\Error;
use Core\Results\Result;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maestros\Application\Productos\CatalogoDeProductos;
use Maestros\Application\Productos\CategoriaDelCatalogo;
use Maestros\Application\Productos\DocumentoTecnico;
use Maestros\Application\Productos\ProductoDelCatalogo;

/**
 * El catálogo es el mismo para todo usuario autenticado: no hay precios ni
 * listas por socio en este desarrollo, así que no pasa por el alcance.
 */
final readonly class ProductosController
{
    public function __construct(private CatalogoDeProductos $catalogo) {}

    public function index(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'categoria' => ['nullable', 'string', Rule::in(array_map(
                static fn (CategoriaDelCatalogo $c): string => $c->codigo,
                $this->catalogo->categorias(),
            ))],
            'pagina' => ['nullable', 'integer', 'min:1'],
            'tamanoPagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pagina = $this->catalogo->pagina(
            isset($datos['categoria']) ? (string) $datos['categoria'] : null,
            isset($datos['pagina']) ? (int) $datos['pagina'] : 1,
            isset($datos['tamanoPagina']) ? (int) $datos['tamanoPagina'] : 20,
        );

        return Envelope::responder(Result::success(), [
            'items' => array_map(self::tarjeta(...), $pagina->items),
            'paginacion' => [
                'pagina' => $pagina->pagina,
                'tamanoPagina' => $pagina->tamanoDePagina,
                'total' => $pagina->total,
                'totalPaginas' => $pagina->totalDePaginas(),
            ],
        ]);
    }

    public function show(string $itemCode): JsonResponse
    {
        $producto = $this->catalogo->producto($itemCode);

        if ($producto === null) {
            return Envelope::responder(Result::failure(self::noEncontrado($itemCode)));
        }

        // `descripcion` no admite null en el contrato: si no hay, no va.
        return Envelope::responder(Result::success(), self::tarjeta($producto)
            + ($producto->descripcion === null ? [] : ['descripcion' => $producto->descripcion])
            + [
                'ingredienteActivo' => $producto->ingredienteActivo,
                'formulacion' => $producto->formulacion,
                'dosisReferencial' => $producto->dosisReferencial,
                'cultivos' => $producto->cultivos,
                'registro' => $producto->registro === null
                    ? null
                    : ['entidad' => $producto->registro->entidad, 'numero' => $producto->registro->numero],
            ]);
    }

    public function documentos(string $itemCode): JsonResponse
    {
        $producto = $this->catalogo->producto($itemCode);

        // El contrato responde 404 tanto si el artículo no existe como si no
        // tiene documentación cargada.
        if ($producto === null || $producto->documentos === []) {
            return Envelope::responder(Result::failure(self::noEncontrado($itemCode)));
        }

        return Envelope::responder(Result::success(), [
            'items' => array_map(
                static fn (DocumentoTecnico $d): array => [
                    'tipo' => $d->tipo->value,
                    'nombre' => $d->tipo->etiqueta().' · '.$producto->nombre,
                    'url' => $d->url,
                ],
                $producto->documentos,
            ),
        ]);
    }

    public function categorias(Request $peticion): JsonResponse
    {
        $data = [
            'items' => array_map(
                static fn (CategoriaDelCatalogo $c): array => ['codigo' => $c->codigo, 'nombre' => $c->nombre],
                $this->catalogo->categorias(),
            ),
        ];

        $respuesta = Envelope::responder(Result::success(), $data)
            ->setEtag(hash('xxh128', (string) json_encode($data)))
            ->setPublic()
            ->setMaxAge(3600);

        // Con el mismo ETag, la App reusa los chips que ya tiene.
        $respuesta->isNotModified($peticion);

        return $respuesta;
    }

    /**
     * `presentacion` no admite null en el contrato: si no hay, no va.
     * `imagenUrl` sí: null le dice a la App que dibuje el ícono de la categoría.
     *
     * @return array<string, mixed>
     */
    private static function tarjeta(ProductoDelCatalogo $producto): array
    {
        return [
            'itemCode' => $producto->itemCode,
            'nombre' => $producto->nombre,
            'categoria' => ['codigo' => $producto->categoria->codigo, 'nombre' => $producto->categoria->nombre],
        ]
            + ($producto->presentacion === null ? [] : ['presentacion' => $producto->presentacion])
            + ['imagenUrl' => $producto->imagenUrl];
    }

    private static function noEncontrado(string $itemCode): Error
    {
        return Error::notFound(
            'RECURSO_NO_ENCONTRADO',
            'El artículo {itemCode} no existe en el catálogo.',
            $itemCode,
        );
    }
}
