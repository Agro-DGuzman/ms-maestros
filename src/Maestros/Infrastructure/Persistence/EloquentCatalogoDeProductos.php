<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Productos\CatalogoDeProductos;
use Maestros\Application\Productos\CategoriaDelCatalogo;
use Maestros\Application\Productos\DocumentoTecnico;
use Maestros\Application\Productos\PaginaDeProductos;
use Maestros\Application\Productos\ProductoDelCatalogo;
use Maestros\Application\Productos\RegistroDelProducto;
use Maestros\Application\Productos\TipoDeDocumento;

/**
 * El catálogo no es réplica de SAP: lo mantiene Agropartners, primero con un
 * script y después desde una web de administración. Por eso no pasa por
 * `vigenteDesde` ni por el importador de maestros.
 */
final class EloquentCatalogoDeProductos implements CatalogoDeProductos
{
    /** Cada tipo de documento es una columna de la tabla. */
    private const array COLUMNAS_DE_DOCUMENTOS = [
        'ficha_tecnica_url' => TipoDeDocumento::FichaTecnica,
        'hoja_seguridad_url' => TipoDeDocumento::HojaDeSeguridad,
        'registro_sanitario_url' => TipoDeDocumento::RegistroSanitario,
    ];

    public function categorias(): array
    {
        $conProductos = $this->visibles()->select('p.codigo_categoria');

        return array_values(array_map(
            fn (object $fila): CategoriaDelCatalogo => new CategoriaDelCatalogo(
                $this->texto((array) $fila, 'codigo'),
                $this->texto((array) $fila, 'nombre'),
            ),
            DB::table($this->tabla('categoria'))
                ->whereIn('codigo', $conProductos)
                ->orderBy('nombre')
                ->get(['codigo', 'nombre'])
                ->all(),
        ));
    }

    public function existeCategoria(string $codigo): bool
    {
        return DB::table($this->tabla('categoria'))->where('codigo', $codigo)->exists();
    }

    public function pagina(?string $codigoDeCategoria, int $pagina, int $tamanoDePagina): PaginaDeProductos
    {
        $consulta = $this->visibles();

        if ($codigoDeCategoria !== null) {
            $consulta->where('p.codigo_categoria', $codigoDeCategoria);
        }

        $total = (clone $consulta)->count();

        $filas = $consulta
            ->orderBy('p.nombre')
            ->orderBy('p.codigo_articulo')
            ->offset(($pagina - 1) * $tamanoDePagina)
            ->limit($tamanoDePagina)
            ->get();

        return new PaginaDeProductos(
            items: array_values(array_map(
                fn (object $fila): ProductoDelCatalogo => $this->aProducto((array) $fila, []),
                $filas->all(),
            )),
            total: $total,
            pagina: $pagina,
            tamanoDePagina: $tamanoDePagina,
        );
    }

    public function producto(string $itemCode): ?ProductoDelCatalogo
    {
        $fila = $this->visibles()->where('p.codigo_articulo', $itemCode)->first();

        if ($fila === null) {
            return null;
        }

        $cultivos = DB::table($this->tabla('producto_cultivo'))
            ->where('id_producto', $this->entero((array) $fila, 'id_producto'))
            ->orderBy('cultivo')
            ->pluck('cultivo')
            ->all();

        return $this->aProducto(
            (array) $fila,
            array_values(array_filter(array_map(
                static fn (mixed $cultivo): string => is_string($cultivo) ? $cultivo : '',
                $cultivos,
            ), static fn (string $cultivo): bool => $cultivo !== '')),
        );
    }

    /**
     * La única definición de «lo que la App puede ver». El join interno con la
     * categoría deja afuera tanto al producto sin categoría como a uno que
     * apunte a una que no existe.
     */
    private function visibles(): Builder
    {
        return DB::table($this->tabla('producto').' as p')
            ->join($this->tabla('categoria').' as c', 'c.codigo', '=', 'p.codigo_categoria')
            ->where('p.activo', true)
            ->whereNotNull('p.codigo_articulo')
            ->select([
                'p.id_producto', 'p.codigo_articulo', 'p.nombre', 'p.presentacion', 'p.imagen_url',
                'p.descripcion', 'p.ingrediente_activo', 'p.formulacion', 'p.dosis_referencial',
                'p.registro_entidad', 'p.registro_numero',
                'p.ficha_tecnica_url', 'p.hoja_seguridad_url', 'p.registro_sanitario_url',
                'c.codigo as categoria_codigo', 'c.nombre as categoria_nombre',
            ]);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  list<string>  $cultivos
     */
    private function aProducto(array $fila, array $cultivos): ProductoDelCatalogo
    {
        $entidad = $this->textoOpcional($fila, 'registro_entidad');
        $numero = $this->textoOpcional($fila, 'registro_numero');

        $documentos = [];

        foreach (self::COLUMNAS_DE_DOCUMENTOS as $columna => $tipo) {
            $url = $this->textoOpcional($fila, $columna);

            if ($url !== null) {
                $documentos[] = new DocumentoTecnico($tipo, $url);
            }
        }

        return new ProductoDelCatalogo(
            itemCode: $this->texto($fila, 'codigo_articulo'),
            nombre: $this->texto($fila, 'nombre'),
            categoria: new CategoriaDelCatalogo(
                $this->texto($fila, 'categoria_codigo'),
                $this->texto($fila, 'categoria_nombre'),
            ),
            presentacion: $this->textoOpcional($fila, 'presentacion'),
            imagenUrl: $this->textoOpcional($fila, 'imagen_url'),
            descripcion: $this->textoOpcional($fila, 'descripcion'),
            ingredienteActivo: $this->textoOpcional($fila, 'ingrediente_activo'),
            formulacion: $this->textoOpcional($fila, 'formulacion'),
            dosisReferencial: $this->textoOpcional($fila, 'dosis_referencial'),
            cultivos: $cultivos,
            // El script exige los dos juntos; si igual llegara uno solo, un
            // registro a medias no se muestra.
            registro: $entidad !== null && $numero !== null ? new RegistroDelProducto($entidad, $numero) : null,
            documentos: $documentos,
        );
    }

    /** @param array<string, mixed> $fila */
    private function texto(array $fila, string $clave): string
    {
        return $this->textoOpcional($fila, $clave) ?? '';
    }

    /**
     * Una cadena vacía cuenta como ausente: el script se carga a mano y un ''
     * en lugar de NULL no debería aparecer en la App como un campo en blanco.
     *
     * @param  array<string, mixed>  $fila
     */
    private function textoOpcional(array $fila, string $clave): ?string
    {
        $valor = $fila[$clave] ?? null;

        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /** @param array<string, mixed> $fila */
    private function entero(array $fila, string $clave): int
    {
        $valor = $fila[$clave] ?? 0;

        return is_numeric($valor) ? (int) $valor : 0;
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
