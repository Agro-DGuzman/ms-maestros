<?php

declare(strict_types=1);

use App\Persistence\ComparacionSinAcentos;
use Maestros\Application\Contactos\BuscadorDeContactos;
use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Productos\CatalogoDeProductos;

/*
 * Estas pruebas afirman qué SQL sale por motor, y a propósito no usan
 * RefreshDatabase: no tocan la base, y cambiar `database.default` mientras esa
 * trait tiene una transacción abierta deja la conexión inservible para el resto
 * del archivo.
 *
 * El comportamiento real —que «Chavez» encuentre a «Chávez»— solo se puede
 * comprobar contra SQL Server, y el host no tiene el driver. Está verificado a
 * mano contra Azure SQL; acá se fija que el COLLATE llegue a la consulta.
 */

afterEach(function () {
    config(['database.default' => 'sqlite']);
});

/** Expone el fragmento protegido para poder afirmar qué sale por driver. */
function comparador(): object
{
    return new class
    {
        use ComparacionSinAcentos;

        public function fragmento(string $columna): string
        {
            return $this->comoTextoInsensible($columna);
        }

        public function patron(string $texto): string
        {
            return $this->patronQueContiene($texto);
        }
    };
}

it('el patron va en minusculas y con los comodines de LIKE escapados', function () {
    // Sin escape, «4%» encontraría todo lo que tenga un 4; `[` abre un rango en SQL Server.
    expect(comparador()->patron('Agrogibe 4%_[X\\'))->toBe('%agrogibe 4\%\_\[x\\\\%');
});

it('el catalogo de la App lleva la colacion al nombre, una vez por palabra', function () {
    config(['database.default' => 'sqlsrv']);

    $catalogo = app(CatalogoDeProductos::class);
    $filtrada = (new ReflectionClass($catalogo))->getMethod('filtrada');

    // Solo se arma la consulta; nunca se ejecuta contra SQL Server.
    $sql = $filtrada->invoke($catalogo, null, 'jisunu 101')->toSql();

    expect(substr_count($sql, "p.nombre COLLATE Latin1_General_CI_AI LIKE ? ESCAPE '\\'"))->toBe(2)
        ->and($sql)->not->toContain('p.codigo_articulo COLLATE');
});

it('en SQL Server compara con una colacion que ignora tildes', function () {
    config(['database.default' => 'sqlsrv']);

    // CI ignora mayúsculas, AI ignora acentos. Sin el AI, un operador que
    // teclea «Chavez» no encuentra a «Chávez»: la colación por defecto de
    // Azure SQL es SQL_Latin1_General_CP1_CI_AS, sensible a los acentos.
    expect(comparador()->fragmento('c.nombre'))
        ->toBe('c.nombre COLLATE Latin1_General_CI_AI');
});

it('en SQLite cae en lower, que es lo unico que ese motor ofrece', function () {
    config(['database.default' => 'sqlite']);

    // Sin ICU, SQLite no tiene colaciones acento-insensibles. Acá el
    // comportamiento es peor a propósito: resuelve la caja y nada más.
    expect(comparador()->fragmento('c.nombre'))
        ->toBe('lower(c.nombre)');
});

it('el buscador lleva la colacion a las dos columnas de texto', function () {
    config(['database.default' => 'sqlsrv']);

    $buscador = app(BuscadorDeContactos::class);
    $consulta = (new ReflectionClass($buscador))->getMethod('consulta');
    $consulta->setAccessible(true);

    // Solo se arma la consulta; nunca se ejecuta contra SQL Server.
    $sql = $consulta->invoke($buscador, CriterioDeBusqueda::de('chavez'))->toSql();

    expect($sql)->toContain('c.nombre COLLATE Latin1_General_CI_AI')
        ->and($sql)->toContain('s.razon_social COLLATE Latin1_General_CI_AI')
        // El celular son dígitos: no tiene acentos ni caja que resolver.
        ->and($sql)->not->toContain('c.celular COLLATE');
});
