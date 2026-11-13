<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * La configuración de la App que las pruebas cargan en la base. Los mismos
 * valores que database/sql/maestros-configuracion-ejemplo.sql, menos lo que
 * tiene un valor por defecto: así las pruebas ven también esos valores.
 */
final class ConfiguracionDeEjemplo
{
    public static function sembrar(): void
    {
        self::cuentas([
            ['banco' => 'Banco Nacional de Bolivia', 'cuenta' => '10000006816033', 'orden' => 1],
            ['banco' => 'Banco BISA', 'cuenta' => '701-5032719-3-81', 'orden' => 2],
        ]);

        self::poner('titular.nit', '1013879029');
        self::poner('atencion.telefono', '67701468');
        self::poner('version.android.minima', '1.8.0');
        self::poner('version.android.recomendada', '2.1.0');
        self::poner('version.android.tienda', 'https://play.google.com/store/apps/details?id=bo.com.agropartners.socio');
    }

    public static function poner(string $clave, string $valor): void
    {
        DB::table(self::tabla('configuracion_app'))->updateOrInsert(['clave' => $clave], ['valor' => $valor]);
    }

    public static function quitar(string $clave): void
    {
        DB::table(self::tabla('configuracion_app'))->where('clave', $clave)->delete();
    }

    /**
     * Reemplaza todas las cuentas por estas.
     *
     * @param  list<array{banco: string, cuenta: string, orden: int}>  $cuentas
     */
    public static function cuentas(array $cuentas): void
    {
        DB::table(self::tabla('cuentas_bancarias'))->delete();

        foreach ($cuentas as $cuenta) {
            DB::table(self::tabla('cuentas_bancarias'))->insert([
                'banco' => $cuenta['banco'],
                'numero_cuenta' => $cuenta['cuenta'],
                'orden' => $cuenta['orden'],
            ]);
        }
    }

    private static function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
