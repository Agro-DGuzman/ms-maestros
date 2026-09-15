<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Core\Results\DomainException;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Contactos\BuscadorDeContactos;
use Maestros\Application\Contactos\ContactoDeBackOffice;
use Maestros\Application\Contactos\CriterioDeBusqueda;
use Maestros\Application\Contactos\FiltroDeEstado;
use Maestros\Application\Contactos\PaginaDeContactos;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Socios\RazonSocial;

final class EloquentBuscadorDeContactos implements BuscadorDeContactos
{
    public function buscar(CriterioDeBusqueda $criterio): PaginaDeContactos
    {
        $total = $this->consulta($criterio)->count();

        $filas = $this->consulta($criterio)
            ->orderBy('c.nombre')
            ->orderBy('c.id_de_persona')
            ->offset($criterio->salto())
            ->limit($criterio->tamanoDePagina)
            ->get();

        return new PaginaDeContactos(
            items: array_values(array_map(
                fn (object $fila): ContactoDeBackOffice => $this->aFila((array) $fila),
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
                'c.id_de_persona', 'c.nombre', 'c.celular', 'c.habilitada_el', 'c.importado_el',
                's.codigo_de_socio', 's.razon_social',
                'g.nombre as grupo_nombre',
            ]);

        $consulta = match ($criterio->estado) {
            FiltroDeEstado::Habilitadas => $consulta->whereNotNull('c.habilitada_el'),
            FiltroDeEstado::NoHabilitadas => $consulta->whereNull('c.habilitada_el'),
            FiltroDeEstado::Todas => $consulta,
        };

        if ($criterio->texto !== null) {
            $patron = '%'.$this->escaparLike($criterio->texto).'%';

            $consulta->where(function (Builder $q) use ($patron): void {
                $q->whereRaw('lower(c.nombre) like lower(?)', [$patron])
                    ->orWhereRaw('lower(s.razon_social) like lower(?)', [$patron])
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

    /** @param array<string, mixed> $fila */
    private function aFila(array $fila): ContactoDeBackOffice
    {
        $celular = $this->textoOpcional($fila, 'celular');
        $nombre = $this->texto($fila, 'nombre');
        $habilitadaEl = $this->momento($fila, 'habilitada_el');

        return new ContactoDeBackOffice(
            idDePersona: $this->texto($fila, 'id_de_persona'),
            nombre: $nombre,
            // Derivadas, nunca almacenadas: una columna de iniciales puede
            // dejar de coincidir con el nombre que abrevia.
            iniciales: (string) RazonSocial::desde($nombre)->iniciales(),
            celular: $celular,
            celularEsValido: $celular !== null && $this->celularEsValido($celular),
            cardCode: $this->texto($fila, 'codigo_de_socio'),
            razonSocial: $this->texto($fila, 'razon_social'),
            grupoEconomico: $this->textoOpcional($fila, 'grupo_nombre'),
            estaHabilitada: $habilitadaEl !== null,
            habilitadaEl: $habilitadaEl,
            // TODO(tarea 12): columna propia. `importado_el` no se actualiza
            // cuando la importación omite una fila por no ser más nueva.
            vistaEnImportacionEl: $this->momento($fila, 'importado_el'),
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

    private function celularEsValido(string $celular): bool
    {
        try {
            Celular::desdeLocalBoliviano($celular);

            return true;
        } catch (DomainException) {
            return false;
        }
    }

    private function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }
}
