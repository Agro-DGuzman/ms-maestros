<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * La única definición de «lo que la App puede ver»: activo, **sin baja**, con
 * código de artículo y con una categoría que existe. El join interno con la
 * categoría deja afuera tanto al producto sin categoría como a uno que apunte
 * a una que no existe. La usan el catálogo y el anuncio de productos nuevos
 * (NP11), y `ProductoAdministrable::porQueNoSeVe()` la dice en palabras: las
 * tres se cambian juntas.
 */
final class VisibilidadDeProductos
{
    public static function consulta(): Builder
    {
        return DB::table(self::tabla('producto').' as p')
            ->join(self::tabla('categoria').' as c', 'c.codigo', '=', 'p.codigo_categoria')
            ->where('p.activo', true)
            ->whereNull('p.dado_de_baja_el')
            ->whereNotNull('p.codigo_articulo')
            ->select([
                'p.id_producto', 'p.codigo_articulo', 'p.nombre', 'p.presentacion', 'p.imagen_url',
                'p.descripcion', 'p.ingrediente_activo', 'p.formulacion', 'p.dosis_referencial',
                'p.registro_entidad', 'p.registro_numero',
                'p.ficha_tecnica_url', 'p.hoja_seguridad_url', 'p.registro_sanitario_url',
                'c.codigo as categoria_codigo', 'c.nombre as categoria_nombre',
            ]);
    }

    private static function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
