<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maestros\Application\Ingesta\CategoriaIngresada;
use Maestros\Application\Ingesta\DarDeBajaProducto\DarDeBajaProducto;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ProductoIngresado;
use Maestros\Application\Ingesta\RegistroIngresado;
use Maestros\Application\Ingesta\ReplicarProducto\ReplicarProducto;
use Maestros\Application\Ingesta\ResultadoDeIngesta;

/**
 * La ingesta del catálogo. Acá se valida la forma del cuerpo (los 400 del
 * contrato); las reglas de negocio son del caso de uso.
 *
 * `descripcion` y los cuatro enlaces no se leen del cuerpo ni siquiera para
 * ignorarlos después: no entran al DTO, así que no hay forma de que un envío
 * de SAP pise lo que cargó el área (P5).
 */
final readonly class IngestaDeProductosController
{
    public function __construct(private Mediator $mediator) {}

    public function crear(Request $pedido): JsonResponse
    {
        return $this->replicar($pedido, OperacionDeIngesta::Crear, null, 201);
    }

    public function reemplazar(Request $pedido, string $itemCode): JsonResponse
    {
        return $this->replicar($pedido, OperacionDeIngesta::Reemplazar, $itemCode, 200);
    }

    public function eliminar(string $itemCode): Response|JsonResponse
    {
        $resultado = $this->mediator->send(new DarDeBajaProducto($itemCode, self::ahora()));

        self::registrar($itemCode, 'eliminar', $resultado->isSuccess ? 'dado-de-baja' : $resultado->error->code, null);

        return $resultado->isSuccess ? response()->noContent() : Envelope::responder($resultado);
    }

    private function replicar(Request $pedido, OperacionDeIngesta $operacion, ?string $itemCodeDeLaRuta, int $statusExito): JsonResponse
    {
        $producto = self::productoIngresado($pedido->validate([
            'itemCode' => ['required', 'string', 'max:50', 'regex:/^[^\/]+$/'],
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['required', 'boolean'],
            'categoria' => ['present', 'nullable', 'array'],
            'categoria.codigo' => ['required_with:categoria', 'string', 'max:50'],
            'categoria.nombre' => ['required_with:categoria', 'string', 'max:255'],
            'presentacion' => ['nullable', 'string', 'max:255'],
            'ingredienteActivo' => ['nullable', 'string', 'max:500'],
            'formulacionCodigo' => ['nullable', 'string', 'max:20'],
            'formulacionDescripcion' => ['nullable', 'string', 'max:200'],
            'dosisReferencial' => ['nullable', 'string', 'max:500'],
            'cultivos' => ['present', 'array'],
            'cultivos.*' => ['nullable', 'string', 'max:255'],
            'registro' => ['nullable', 'array'],
            'registro.entidad' => ['required_with:registro', 'in:SENASAG,INIAF,otro'],
            'registro.numero' => ['required_with:registro', 'string', 'max:100'],
            'vigenteDesde' => ['required', 'date'],
            'origen.esquema' => ['required', 'string', 'max:20'],
            'origen.eventoId' => ['required', 'integer', 'min:0'],
        ]));

        $resultado = $this->mediator->send(new ReplicarProducto($operacion, $itemCodeDeLaRuta, $producto));

        $detalle = $resultado instanceof ResultWithValue && $resultado->isSuccess && $resultado->value() instanceof ResultadoDeIngesta
            ? $resultado->value()->value
            : $resultado->error->code;

        self::registrar(
            $producto->itemCode,
            $operacion === OperacionDeIngesta::Crear ? 'crear' : 'reemplazar',
            $detalle,
            $producto->origenEventoId,
        );

        return Envelope::responder($resultado, ['itemCode' => $producto->itemCode], $statusExito);
    }

    /** @param array<string, mixed> $datos ya validados */
    private static function productoIngresado(array $datos): ProductoIngresado
    {
        $categoria = is_array($datos['categoria'] ?? null) ? $datos['categoria'] : null;
        $registro = is_array($datos['registro'] ?? null) ? $datos['registro'] : null;
        $origen = is_array($datos['origen'] ?? null) ? $datos['origen'] : [];
        $cultivos = is_array($datos['cultivos'] ?? null) ? $datos['cultivos'] : [];

        return new ProductoIngresado(
            itemCode: self::texto($datos, 'itemCode'),
            nombre: self::texto($datos, 'nombre'),
            activo: (bool) ($datos['activo'] ?? false),
            categoria: $categoria === null
                ? null
                : CategoriaIngresada::de(self::texto($categoria, 'codigo'), self::texto($categoria, 'nombre')),
            presentacion: self::textoOpcional($datos, 'presentacion'),
            ingredienteActivo: self::textoOpcional($datos, 'ingredienteActivo'),
            formulacionCodigo: self::textoOpcional($datos, 'formulacionCodigo'),
            formulacionDescripcion: self::textoOpcional($datos, 'formulacionDescripcion'),
            dosisReferencial: self::textoOpcional($datos, 'dosisReferencial'),
            // Un cultivo vacío se descarta en vez de guardarse: sería una fila
            // con el cultivo en blanco en la tarjeta de la App.
            cultivos: array_values(array_filter(
                array_map(static fn (mixed $c): string => is_scalar($c) ? trim((string) $c) : '', $cultivos),
                static fn (string $c): bool => $c !== '',
            )),
            registro: $registro === null
                ? null
                : new RegistroIngresado(self::texto($registro, 'entidad'), self::texto($registro, 'numero')),
            // En UTC desde la frontera: guardada con su desfase, 08:00-04:00
            // quedaría como las 08:00 y un envío más viejo parecería más nuevo.
            vigenteDesde: (new DateTimeImmutable(self::texto($datos, 'vigenteDesde')))->setTimezone(new DateTimeZone('UTC')),
            origenEsquema: self::texto($origen, 'esquema'),
            origenEventoId: is_numeric($origen['eventoId'] ?? null) ? (int) $origen['eventoId'] : 0,
        );
    }

    /** @param array<mixed> $datos */
    private static function texto(array $datos, string $clave): string
    {
        $valor = $datos[$clave] ?? '';

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    /**
     * Vacío o con solo espacios es ausente: así no borra lo guardado (P9).
     *
     * @param  array<mixed>  $datos
     */
    private static function textoOpcional(array $datos, string $clave): ?string
    {
        $texto = self::texto($datos, $clave);

        return $texto === '' ? null : $texto;
    }

    private static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Una línea por envío, para cruzarla con el log del Sincronizador. */
    private static function registrar(string $itemCode, string $operacion, string $resultado, ?int $eventoId): void
    {
        Log::info('ingesta', [
            'itemCode' => $itemCode,
            'operacion' => $operacion,
            'resultado' => $resultado,
            'eventoId' => $eventoId,
        ]);
    }
}
