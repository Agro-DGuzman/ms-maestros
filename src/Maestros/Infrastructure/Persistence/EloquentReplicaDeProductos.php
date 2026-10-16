<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\FechaEnUtc;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Ingesta\EstadoDeProductoReplicado;
use Maestros\Application\Ingesta\ProductoIngresado;
use Maestros\Application\Ingesta\ReplicaDeProductos;

/**
 * Escribe solo las columnas de SAP: la descripción, los cuatro enlaces y el
 * `wp_id` no se nombran en ningún `update`, así un envío no puede pisar lo que
 * cargó el área (P5).
 */
final class EloquentReplicaDeProductos implements ReplicaDeProductos
{
    use FechaEnUtc;

    public function estado(string $itemCode): ?EstadoDeProductoReplicado
    {
        $fila = DB::table($this->tabla('producto'))
            ->where('codigo_articulo', $itemCode)
            ->first(['vigente_desde', 'dado_de_baja_el']);

        if ($fila === null) {
            return null;
        }

        $columnas = (array) $fila;

        return new EstadoDeProductoReplicado(
            $this->fecha($columnas, 'vigente_desde'),
            ($columnas['dado_de_baja_el'] ?? null) !== null,
        );
    }

    public function replicar(ProductoIngresado $producto): void
    {
        if ($producto->categoria !== null) {
            $this->asegurarCategoria($producto->categoria->codigo, $producto->categoria->nombre);
        }

        // Obligatorios en el cuerpo, así que siempre se escriben. La baja se
        // limpia: un POST más nuevo que la baja reactiva el producto.
        $columnas = [
            'nombre' => $producto->nombre,
            'activo' => $producto->activo,
            'vigente_desde' => self::enUtc($producto->vigenteDesde),
            'dado_de_baja_el' => null,
            'origen_esquema' => $producto->origenEsquema,
            'origen_evento_id' => $producto->origenEventoId,
            'actualizado_en' => now(),
        ];

        // P9: lo que no trae valor no se escribe, para no borrar la ficha que
        // cargó la web. El registro va junto: entidad y número son un dato.
        $opcionales = [
            'presentacion' => $producto->presentacion,
            'codigo_categoria' => $producto->categoria?->codigo,
            'ingrediente_activo' => $producto->ingredienteActivo,
            'formulacion' => $producto->formulacion(),
            'dosis_referencial' => $producto->dosisReferencial,
        ];

        foreach ($opcionales as $columna => $valor) {
            if ($valor !== null) {
                $columnas[$columna] = $valor;
            }
        }

        if ($producto->registro !== null) {
            $columnas['registro_entidad'] = $producto->registro->entidad;
            $columnas['registro_numero'] = $producto->registro->numero;
        }

        $tabla = DB::table($this->tabla('producto'));
        $id = $tabla->where('codigo_articulo', $producto->itemCode)->value('id_producto');

        if (is_numeric($id)) {
            $tabla->where('id_producto', $id)->update($columnas);
        } else {
            $id = DB::table($this->tabla('producto'))->insertGetId(
                $columnas + ['codigo_articulo' => $producto->itemCode],
                'id_producto',
            );
        }

        $this->escribirCultivos((int) $id, $producto->cultivosSinRepetir());
    }

    public function darDeBaja(string $itemCode, DateTimeImmutable $momento): void
    {
        DB::table($this->tabla('producto'))->where('codigo_articulo', $itemCode)->update([
            'dado_de_baja_el' => self::enUtc($momento),
            'vigente_desde' => self::enUtc($momento),
            'actualizado_en' => now(),
        ]);
    }

    /**
     * El nombre de una categoría que ya existe no se toca (P4): SAP la asigna,
     * pero no la renombra.
     *
     * Dos envíos que crean la misma categoría nueva a la vez no pueden fallar:
     * el segundo tolera la violación de unicidad y sigue. No se usa
     * `insertOrIgnore` porque la gramática de SQL Server de Laravel no lo
     * tiene: pasaría en SQLite y daría 500 en Azure.
     */
    private function asegurarCategoria(string $codigo, string $nombre): void
    {
        $categorias = DB::table($this->tabla('categoria'));

        if ($categorias->where('codigo', $codigo)->exists()) {
            return;
        }

        try {
            $categorias->insert(['codigo' => $codigo, 'nombre' => $nombre]);
        } catch (UniqueConstraintViolationException) {
            // La creó el otro envío entre la consulta y la escritura.
        }
    }

    /** @param list<string> $cultivos */
    private function escribirCultivos(int $id, array $cultivos): void
    {
        if ($cultivos === []) {
            return;
        }

        DB::table($this->tabla('producto_cultivo'))->where('id_producto', $id)->delete();

        DB::table($this->tabla('producto_cultivo'))->insert(array_map(
            static fn (string $cultivo): array => ['id_producto' => $id, 'cultivo' => $cultivo],
            $cultivos,
        ));
    }

    /** @param array<string, mixed> $columnas */
    private function fecha(array $columnas, string $clave): ?DateTimeImmutable
    {
        $valor = $columnas[$clave] ?? null;

        return is_string($valor) && $valor !== '' ? new DateTimeImmutable($valor) : null;
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
