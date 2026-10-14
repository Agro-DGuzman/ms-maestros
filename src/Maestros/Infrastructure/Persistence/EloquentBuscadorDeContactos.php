<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use App\Persistence\ComparacionSinAcentos;
use Core\Results\DomainException;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Contactos\BuscadorDeContactos;
use Maestros\Application\Contactos\ContactoDeBackOffice;
use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Contactos\PaginaDeContactos;
use Maestros\Domain\Contactos\AlcanceDeNumero;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\RazonSocial;

final class EloquentBuscadorDeContactos implements BuscadorDeContactos
{
    use ComparacionSinAcentos;

    public function __construct(private ContactoRepository $contactos) {}

    public function buscar(CriterioDeBusqueda $criterio): PaginaDeContactos
    {
        $total = $this->consulta($criterio)->count();

        $filas = $this->consulta($criterio)
            ->orderBy('c.nombre')
            ->orderBy('c.id_de_persona')
            ->offset($criterio->salto())
            ->limit($criterio->tamanoDePagina)
            ->get();

        // Todos los números de la página en una consulta: varias filas pueden
        // ser la misma persona, y la regla de si entra vive en un solo lugar.
        $celulares = [];
        foreach ($filas as $fila) {
            $celular = $this->celularValido($this->textoOpcional((array) $fila, 'celular'));
            if ($celular !== null) {
                $celulares[] = $celular;
            }
        }
        $alcances = $this->contactos->alcancesDeNumeros($celulares);

        return new PaginaDeContactos(
            items: array_values(array_map(
                fn (object $fila): ContactoDeBackOffice => $this->aFila((array) $fila, $alcances),
                $filas->all(),
            )),
            total: $total,
            pagina: $criterio->pagina,
            tamanoDePagina: $criterio->tamanoDePagina,
        );
    }

    private function consulta(CriterioDeBusqueda $criterio): Builder
    {
        $consulta = DB::table($this->tabla('contactos').' as c')
            ->join($this->tabla('socios').' as s', 's.codigo_de_socio', '=', 'c.codigo_de_socio')
            // leftJoin: un socio puede apuntar a un grupo que la réplica
            // todavía no trajo, y esa fila igual tiene que listarse.
            ->leftJoin($this->tabla('grupos').' as g', 'g.id_de_grupo', '=', 's.id_de_grupo')
            ->select([
                'c.id_de_persona', 'c.nombre', 'c.celular',
                'c.activo', 'c.dado_de_baja_el', 's.activo as socio_activo', 's.dado_de_baja_el as socio_dado_de_baja_el',
                's.codigo_de_socio', 's.razon_social',
                'g.nombre as grupo_nombre',
            ])
            // Cuántos contactos visibles tienen este celular: son la misma
            // persona, y la pantalla lo dice en cada fila.
            ->selectSub(function (Builder $q): void {
                $q->from($this->tabla('contactos').' as c2')
                    ->join($this->tabla('socios').' as s2', 's2.codigo_de_socio', '=', 'c2.codigo_de_socio')
                    ->whereColumn('c2.celular', 'c.celular')
                    ->selectRaw('count(*)');

                Visibilidad::exigir($q, 'c2', 's2');
            }, 'compartido');

        if ($criterio->texto !== null) {
            // El patrón va en minúsculas desde PHP: en SQLite la comparación es
            // `lower(columna)` y necesita el otro lado igual. En SQL Server la
            // colación ya ignora la caja y esto no le cambia nada.
            $patron = '%'.$this->escaparLike(mb_strtolower($criterio->texto)).'%';
            $nombre = $this->comoTextoInsensible('c.nombre');
            $razonSocial = $this->comoTextoInsensible('s.razon_social');

            // La expresión va del lado de la columna y el patrón sigue siendo un
            // parámetro ligado. Con `whereRaw()` habría que armar la cadena
            // completa, y ahí PHPStan exige `literal-string` justamente para
            // que nadie construya SQL con lo que tecleó el operador.
            $consulta->where(function (Builder $q) use ($patron, $nombre, $razonSocial): void {
                // El celular no pasa por la colación: son dígitos, no tiene
                // acentos ni caja que resolver.
                $q->where(DB::raw($nombre), 'like', $patron)
                    ->orWhere(DB::raw($razonSocial), 'like', $patron)
                    ->orWhere('c.celular', 'like', $patron);
            });
        }

        return $consulta;
    }

    /** Sin esto, un `%` tecleado por el operador lista la tabla entera. */
    private function escaparLike(string $texto): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $texto);
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, AlcanceDeNumero>  $alcances  por celular en E.164
     */
    private function aFila(array $fila, array $alcances): ContactoDeBackOffice
    {
        $celular = $this->textoOpcional($fila, 'celular');
        $numero = $this->celularValido($celular);
        $nombre = $this->texto($fila, 'nombre');
        $estado = $this->estado($fila);

        return new ContactoDeBackOffice(
            idDePersona: $this->texto($fila, 'id_de_persona'),
            nombre: $nombre,
            // Derivadas, nunca almacenadas: una columna de iniciales puede
            // dejar de coincidir con el nombre que abrevia.
            iniciales: (string) RazonSocial::desde($nombre)->iniciales(),
            celular: $celular,
            celularEsValido: $numero !== null,
            cardCode: $this->texto($fila, 'codigo_de_socio'),
            razonSocial: $this->texto($fila, 'razon_social'),
            grupoEconomico: $this->textoOpcional($fila, 'grupo_nombre'),
            estado: $estado,
            personaDelNumero: $numero === null ? null : IdDePersona::deCelular($numero)->value(),
            // `compartido` cuenta los visibles con este número, esta fila incluida si lo es.
            compartidoCon: max(0, $this->entero($fila, 'compartido') - ($estado === 'visible' ? 1 : 0)),
            alcanceDelNumero: $numero === null ? null : ($alcances[$numero->e164()] ?? null),
        );
    }

    /**
     * Las filas llegan del motor sin tipo: se leen validando, igual que
     * cualquier otra entrada externa.
     *
     * @param  array<string, mixed>  $fila
     */
    private function texto(array $fila, string $clave): string
    {
        return $this->textoOpcional($fila, $clave) ?? '';
    }

    /** @param array<string, mixed> $fila */
    private function textoOpcional(array $fila, string $clave): ?string
    {
        $valor = $fila[$clave] ?? null;

        if (! is_string($valor) && ! is_int($valor) && ! is_float($valor)) {
            return null;
        }

        $texto = (string) $valor;

        return $texto === '' ? null : $texto;
    }

    /** @param array<string, mixed> $fila */
    private function momento(array $fila, string $clave): ?DateTimeImmutable
    {
        $texto = $this->textoOpcional($fila, $clave);

        return $texto === null ? null : new DateTimeImmutable($texto);
    }

    /**
     * Lo que la App ve de esta persona, contando también a su socio. La baja
     * pesa más que la inactividad: es lo que hay que contarle al operador.
     *
     * @param  array<string, mixed>  $fila
     */
    private function estado(array $fila): string
    {
        if ($this->momento($fila, 'dado_de_baja_el') !== null || $this->momento($fila, 'socio_dado_de_baja_el') !== null) {
            return 'dado-de-baja';
        }

        return $this->entero($fila, 'activo') === 1 && $this->entero($fila, 'socio_activo') === 1 ? 'visible' : 'inactivo';
    }

    /** @param array<string, mixed> $fila */
    private function entero(array $fila, string $clave): int
    {
        $valor = $fila[$clave] ?? 0;

        return is_numeric($valor) ? (int) $valor : (is_bool($valor) ? (int) $valor : 0);
    }

    private function celularValido(?string $celular): ?Celular
    {
        if ($celular === null) {
            return null;
        }

        try {
            return Celular::desdeLocalBoliviano($celular);
        } catch (DomainException) {
            return null;
        }
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
