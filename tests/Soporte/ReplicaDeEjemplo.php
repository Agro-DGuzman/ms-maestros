<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Illuminate\Support\Facades\DB;

/**
 * Lleva la semilla importada a los estados que la ingesta puede dejar: socios
 * sin grupo, inactivos o dados de baja, personas inactivas, y un celular que
 * comparten dos contactos. Escribe directo en la tabla para no depender de los
 * casos de uso que se están probando.
 */
final class ReplicaDeEjemplo
{
    public static function sinGrupo(string $cardCode): void
    {
        self::socio($cardCode, ['id_de_grupo' => null]);
    }

    public static function socioInactivo(string $cardCode): void
    {
        self::socio($cardCode, ['activo' => false]);
    }

    public static function socioDadoDeBaja(string $cardCode): void
    {
        self::socio($cardCode, ['dado_de_baja_el' => '2026-10-05 12:00:00']);
    }

    public static function personaInactiva(string $id): void
    {
        self::contacto($id, ['activo' => false]);
    }

    public static function personaDadaDeBaja(string $id): void
    {
        self::contacto($id, ['dado_de_baja_el' => '2026-10-05 12:00:00']);
    }

    /** Otro contacto visible, en un socio de otro grupo, con el mismo número. */
    public static function otroContactoCon(string $celular, string $id = 'p-9999', bool $dadoDeBaja = false): void
    {
        DB::table(self::tabla('contactos'))->insert([
            'id_de_persona' => $id,
            'codigo_de_socio' => 'C-005210',
            'nombre' => 'Otra persona',
            'celular' => $celular,
            'vigente_desde' => '2026-10-05 12:00:00',
            'importado_el' => '2026-10-05 12:00:00',
            'activo' => true,
            'dado_de_baja_el' => $dadoDeBaja ? '2026-10-05 12:00:00' : null,
        ]);
    }

    /** @param array<string, mixed> $columnas */
    private static function socio(string $cardCode, array $columnas): void
    {
        DB::table(self::tabla('socios'))->where('codigo_de_socio', $cardCode)->update($columnas);
    }

    /** @param array<string, mixed> $columnas */
    private static function contacto(string $id, array $columnas): void
    {
        DB::table(self::tabla('contactos'))->where('id_de_persona', $id)->update($columnas);
    }

    private static function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
