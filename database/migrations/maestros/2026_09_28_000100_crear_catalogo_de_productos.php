<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * En SQL Server la estructura es la de database/sql/maestros-productos.sql, que
 * es también el script con el que se cargan los datos: ejecutarlo acá deja un
 * solo lugar donde vive. Es idempotente, así que da igual si alguien lo corrió
 * a mano antes.
 *
 * SQLite, que es solo para las pruebas, no entiende T-SQL: recibe la misma
 * forma armada con Blueprint, sin los CHECK del script.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlsrv') {
            foreach ($this->lotesDelScript() as $lote) {
                DB::unprepared($lote);
            }

            return;
        }

        Schema::create('maestros_categoria', function (Blueprint $tabla): void {
            $tabla->string('codigo', 50)->primary();
            $tabla->string('nombre', 255);
        });

        Schema::create('maestros_producto', function (Blueprint $tabla): void {
            $tabla->increments('id_producto');
            $tabla->string('codigo_articulo', 50)->nullable()->unique();
            $tabla->string('nombre', 255);
            $tabla->string('codigo_categoria', 50)->nullable();
            $tabla->string('presentacion', 255)->nullable();
            $tabla->text('descripcion')->nullable();
            $tabla->string('ingrediente_activo', 500)->nullable();
            $tabla->string('formulacion', 255)->nullable();
            $tabla->string('dosis_referencial', 500)->nullable();
            $tabla->string('registro_entidad', 10)->nullable();
            $tabla->string('registro_numero', 100)->nullable();
            $tabla->string('imagen_url', 1000)->nullable();
            $tabla->string('ficha_tecnica_url', 1000)->nullable();
            $tabla->string('hoja_seguridad_url', 1000)->nullable();
            $tabla->string('registro_sanitario_url', 1000)->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->integer('wp_id')->nullable()->unique();
            $tabla->dateTime('creado_en')->useCurrent();
            $tabla->dateTime('actualizado_en')->useCurrent();

            $tabla->foreign('codigo_categoria')->references('codigo')->on('maestros_categoria');
        });

        Schema::create('maestros_producto_cultivo', function (Blueprint $tabla): void {
            $tabla->unsignedInteger('id_producto');
            $tabla->string('cultivo', 255);
            $tabla->primary(['id_producto', 'cultivo']);

            $tabla->foreign('id_producto')->references('id_producto')->on('maestros_producto')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        $prefijo = DB::getDriverName() === 'sqlsrv' ? 'maestros.' : 'maestros_';

        Schema::dropIfExists($prefijo.'producto_cultivo');
        Schema::dropIfExists($prefijo.'producto');
        Schema::dropIfExists($prefijo.'categoria');
    }

    /**
     * `GO` no es T-SQL: es el separador de lotes de sqlcmd y de SSMS. Hay que
     * partir el script ahí, porque algunas sentencias tienen que ir solas.
     *
     * @return list<string>
     */
    private function lotesDelScript(): array
    {
        $script = file_get_contents(database_path('sql/maestros-productos.sql'));

        if ($script === false) {
            throw new RuntimeException('No se pudo leer database/sql/maestros-productos.sql');
        }

        $lotes = preg_split('/^\s*GO\s*$/mi', $script) ?: [];

        return array_values(array_filter(
            array_map('trim', $lotes),
            static fn (string $lote): bool => $lote !== '' && ! self::soloComentarios($lote),
        ));
    }

    private static function soloComentarios(string $lote): bool
    {
        $sinBloques = preg_replace('#/\*.*?\*/#s', '', $lote) ?? $lote;
        $sinLineas = preg_replace('/^\s*--.*$/m', '', $sinBloques) ?? $sinBloques;

        return trim($sinLineas) === '';
    }
};
