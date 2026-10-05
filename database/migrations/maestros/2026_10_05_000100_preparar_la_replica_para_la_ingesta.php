<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La ingesta trae lo que el importador nunca vio: socios sin grupo, bajas,
 * inactivos, contactos sin celular o con un celular repetido. Todo es aditivo
 * o afloja una restricción, así que el código que corre hoy sigue andando con
 * la base nueva y la migración se puede correr antes del despliegue.
 *
 * `vista_en_importacion_el` no se toca: el código actual la lee, y borrarla
 * antes de desplegar rompería la pantalla de Contactos en el intervalo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            $this->ensancharEnSqlServer();
        } else {
            Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
                $tabla->string('id_de_grupo', 50)->nullable()->change();
            });

            Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
                $tabla->dropUnique(['celular']);
            });

            Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
                $tabla->string('celular', 20)->nullable()->change();
                $tabla->index('celular');
            });
        }

        Schema::table($this->tabla('grupos'), function (Blueprint $tabla): void {
            $tabla->string('segmento', 100)->nullable();
        });

        Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->boolean('activo')->default(true);
            $tabla->dateTimeTz('dado_de_baja_el')->nullable();
            $tabla->string('origen_esquema', 20)->nullable();
            $tabla->unsignedBigInteger('origen_evento_id')->nullable();
        });

        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->boolean('activo')->default(true);
            $tabla->dateTimeTz('dado_de_baja_el')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->dropColumn(['activo', 'dado_de_baja_el']);
        });

        Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->dropColumn(['activo', 'dado_de_baja_el', 'origen_esquema', 'origen_evento_id']);
        });

        Schema::table($this->tabla('grupos'), function (Blueprint $tabla): void {
            $tabla->dropColumn('segmento');
        });

        if (DB::getDriverName() === 'sqlsrv') {
            $this->angostarEnSqlServer();

            return;
        }

        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->dropIndex(['celular']);
        });

        Schema::table($this->tabla('contactos'), function (Blueprint $tabla): void {
            $tabla->string('celular', 20)->nullable(false)->change();
            $tabla->unique('celular');
        });

        Schema::table($this->tabla('socios'), function (Blueprint $tabla): void {
            $tabla->string('id_de_grupo', 40)->nullable(false)->change();
        });
    }

    /**
     * SQL Server no deja alterar una columna que está en una clave primaria o
     * en un índice: hay que soltarlos, alterar y volver a crearlos. Los nombres
     * se buscan en el catálogo en vez de suponer los que generó Laravel, que en
     * una base creada a mano podrían ser otros.
     */
    private function ensancharEnSqlServer(): void
    {
        $this->soltarClavePrimaria('maestros.grupos');
        DB::statement('ALTER TABLE maestros.grupos ALTER COLUMN id_de_grupo NVARCHAR(50) NOT NULL');
        DB::statement('ALTER TABLE maestros.grupos ADD CONSTRAINT PK_grupos PRIMARY KEY (id_de_grupo)');

        $this->soltarIndicesDe('maestros.socios', 'id_de_grupo');
        DB::statement('ALTER TABLE maestros.socios ALTER COLUMN id_de_grupo NVARCHAR(50) NULL');
        DB::statement('CREATE INDEX IX_socios_grupo ON maestros.socios (id_de_grupo)');

        $this->soltarIndicesDe('maestros.contactos', 'celular');
        DB::statement('ALTER TABLE maestros.contactos ALTER COLUMN celular NVARCHAR(20) NULL');
        DB::statement('CREATE INDEX IX_contactos_celular ON maestros.contactos (celular)');
    }

    private function angostarEnSqlServer(): void
    {
        $this->soltarIndicesDe('maestros.contactos', 'celular');
        DB::statement('ALTER TABLE maestros.contactos ALTER COLUMN celular NVARCHAR(20) NOT NULL');
        DB::statement('CREATE UNIQUE INDEX maestros_contactos_celular_unique ON maestros.contactos (celular)');

        $this->soltarIndicesDe('maestros.socios', 'id_de_grupo');
        DB::statement('ALTER TABLE maestros.socios ALTER COLUMN id_de_grupo NVARCHAR(40) NOT NULL');
        DB::statement('CREATE INDEX maestros_socios_id_de_grupo_index ON maestros.socios (id_de_grupo)');

        $this->soltarClavePrimaria('maestros.grupos');
        DB::statement('ALTER TABLE maestros.grupos ALTER COLUMN id_de_grupo NVARCHAR(40) NOT NULL');
        DB::statement('ALTER TABLE maestros.grupos ADD CONSTRAINT PK_grupos PRIMARY KEY (id_de_grupo)');
    }

    private function soltarClavePrimaria(string $tabla): void
    {
        $nombre = DB::scalar(
            "SELECT name FROM sys.key_constraints WHERE parent_object_id = OBJECT_ID(?) AND type = 'PK'",
            [$tabla],
        );

        if (is_string($nombre)) {
            DB::statement("ALTER TABLE {$tabla} DROP CONSTRAINT [{$nombre}]");
        }
    }

    /** Índices comunes y únicos; nunca la clave primaria. */
    private function soltarIndicesDe(string $tabla, string $columna): void
    {
        $nombres = DB::select(
            'SELECT DISTINCT i.name FROM sys.indexes i
               JOIN sys.index_columns ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
               JOIN sys.columns c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
              WHERE i.object_id = OBJECT_ID(?) AND c.name = ? AND i.is_primary_key = 0',
            [$tabla, $columna],
        );

        foreach ($nombres as $fila) {
            $nombre = ((array) $fila)['name'] ?? null;

            if (is_string($nombre)) {
                DB::statement("DROP INDEX [{$nombre}] ON {$tabla}");
            }
        }
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
};
