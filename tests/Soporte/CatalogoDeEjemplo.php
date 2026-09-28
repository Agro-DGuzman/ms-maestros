<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * Un catálogo chico que cubre cada caso de visibilidad: tres productos que la
 * App tiene que ver y tres que no, cada uno oculto por una razón distinta.
 */
final class CatalogoDeEjemplo
{
    public static function sembrar(): void
    {
        $gliforte = self::producto([
            'codigo_articulo' => 'A-0142',
            'nombre' => 'Gliforte 68 SG',
            'codigo_categoria' => 'herbicidas',
            'presentacion' => 'Bolsa 10 Kg',
            'descripcion' => 'Herbicida sistémico no selectivo de amplio espectro.',
            'ingrediente_activo' => 'Glifosato sal de amonio 68%',
            'formulacion' => 'Gránulos solubles (SG)',
            'dosis_referencial' => '1,5 – 3,0 Kg/ha',
            'registro_entidad' => 'SENASAG',
            'registro_numero' => '1842-H',
            'imagen_url' => 'https://cdn.agropartners.com.bo/productos/A-0142.webp',
            'ficha_tecnica_url' => 'https://docs.agropartners.com.bo/A-0142-tds.pdf',
            'hoja_seguridad_url' => 'https://docs.agropartners.com.bo/A-0142-msds.pdf',
        ]);
        self::cultivos($gliforte, ['Soja', 'Barbecho químico']);

        self::producto([
            'codigo_articulo' => 'S-0101',
            'nombre' => 'Sorgo Jisunú 101',
            'codigo_categoria' => 'semillas',
            'presentacion' => 'Bolsa 20 Kg',
            'registro_entidad' => 'INIAF',
            'registro_numero' => 'SG-101',
            'registro_sanitario_url' => 'https://docs.agropartners.com.bo/S-0101-registro.pdf',
        ]);

        // Lo mínimo que puede tener un producto visible: sin presentación, sin
        // registro, sin imagen y sin documentos.
        self::producto([
            'codigo_articulo' => 'A-0219',
            'nombre' => 'Atrazina 90 WG',
            'codigo_categoria' => 'herbicidas',
        ]);

        self::producto([
            'codigo_articulo' => 'A-0300',
            'nombre' => 'Dado de baja',
            'codigo_categoria' => 'herbicidas',
            'activo' => false,
            'ficha_tecnica_url' => 'https://docs.agropartners.com.bo/A-0300-tds.pdf',
        ]);
        self::producto(['codigo_articulo' => null, 'nombre' => 'Sin código SAP todavía', 'codigo_categoria' => 'herbicidas']);
        self::producto(['codigo_articulo' => 'A-0400', 'nombre' => 'Sin categoría todavía', 'codigo_categoria' => null]);
    }

    public static function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }

    /** @param array<string, mixed> $columnas */
    private static function producto(array $columnas): int
    {
        return (int) DB::table(self::tabla('producto'))->insertGetId($columnas, 'id_producto');
    }

    /** @param list<string> $cultivos */
    private static function cultivos(int $producto, array $cultivos): void
    {
        foreach ($cultivos as $cultivo) {
            DB::table(self::tabla('producto_cultivo'))->insert(['id_producto' => $producto, 'cultivo' => $cultivo]);
        }
    }
}
