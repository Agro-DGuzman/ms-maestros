<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\ComparacionSinAcentos;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\EnlacesDeProducto;
use Maestros\Application\Productos\FiltroDeEnlaces;
use Maestros\Application\Productos\ProductoAdministrable;

/**
 * Lista todo el catálogo, también lo que la App no ve: el área tiene que poder
 * cargar la imagen de un producto antes de que tenga código o categoría.
 */
final class EloquentCatalogoAdministrable implements CatalogoAdministrable
{
    use ComparacionSinAcentos;

    public function listar(?string $texto, FiltroDeEnlaces $filtro): array
    {
        $consulta = $this->consulta();

        $texto = $texto === null ? '' : trim($texto);

        if ($texto !== '') {
            // El patrón va en minúsculas y escapado, como en la búsqueda de
            // contactos: sin escapar, un `%` tecleado lista todo.
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($texto)).'%';
            $nombre = $this->comoTextoInsensible('p.nombre');
            $codigo = $this->comoTextoInsensible('p.codigo_articulo');

            $consulta->where(function (Builder $q) use ($patron, $nombre, $codigo): void {
                $q->where(DB::raw($nombre), 'like', $patron)
                    ->orWhere(DB::raw($codigo), 'like', $patron);
            });
        }

        match ($filtro) {
            FiltroDeEnlaces::SinImagen => $this->sinValor($consulta, 'p.imagen_url'),
            FiltroDeEnlaces::SinDocumentos => $consulta->where(function (Builder $q): void {
                $this->sinValor($q, 'p.ficha_tecnica_url');
                $this->sinValor($q, 'p.hoja_seguridad_url');
                $this->sinValor($q, 'p.registro_sanitario_url');
            }),
            FiltroDeEnlaces::Todos => null,
        };

        return array_values(array_map(
            fn (object $fila): ProductoAdministrable => $this->aProducto((array) $fila),
            $consulta->orderBy('p.nombre')->orderBy('p.id_producto')->get()->all(),
        ));
    }

    public function buscar(int $id): ?ProductoAdministrable
    {
        $fila = $this->consulta()->where('p.id_producto', $id)->first();

        return $fila === null ? null : $this->aProducto((array) $fila);
    }

    public function guardarEnlaces(int $id, EnlacesDeProducto $enlaces): void
    {
        DB::table($this->tabla('producto'))->where('id_producto', $id)->update([
            'imagen_url' => $enlaces->imagen,
            'ficha_tecnica_url' => $enlaces->fichaTecnica,
            'hoja_seguridad_url' => $enlaces->hojaDeSeguridad,
            'registro_sanitario_url' => $enlaces->registroSanitario,
            'actualizado_en' => now(),
        ]);
    }

    /** El left join deja la categoría en null si falta o si apunta a una que no existe. */
    private function consulta(): Builder
    {
        return DB::table($this->tabla('producto').' as p')
            ->leftJoin($this->tabla('categoria').' as c', 'c.codigo', '=', 'p.codigo_categoria')
            ->select([
                'p.id_producto', 'p.codigo_articulo', 'p.nombre', 'p.activo',
                'p.imagen_url', 'p.ficha_tecnica_url', 'p.hoja_seguridad_url', 'p.registro_sanitario_url',
                'c.nombre as categoria_nombre',
            ]);
    }

    /** Vacío cuenta como ausente: una carga a mano puede dejar '' en vez de NULL. */
    private function sinValor(Builder $consulta, string $columna): void
    {
        $consulta->where(fn (Builder $q) => $q->whereNull($columna)->orWhere($columna, ''));
    }

    /** @param array<string, mixed> $fila */
    private function aProducto(array $fila): ProductoAdministrable
    {
        return new ProductoAdministrable(
            id: is_numeric($fila['id_producto'] ?? null) ? (int) $fila['id_producto'] : 0,
            itemCode: $this->textoOpcional($fila, 'codigo_articulo'),
            nombre: $this->textoOpcional($fila, 'nombre') ?? '',
            categoria: $this->textoOpcional($fila, 'categoria_nombre'),
            activo: (bool) ($fila['activo'] ?? false),
            enlaces: new EnlacesDeProducto(
                $this->textoOpcional($fila, 'imagen_url'),
                $this->textoOpcional($fila, 'ficha_tecnica_url'),
                $this->textoOpcional($fila, 'hoja_seguridad_url'),
                $this->textoOpcional($fila, 'registro_sanitario_url'),
            ),
        );
    }

    /** @param array<string, mixed> $fila */
    private function textoOpcional(array $fila, string $clave): ?string
    {
        $valor = $fila[$clave] ?? null;

        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
