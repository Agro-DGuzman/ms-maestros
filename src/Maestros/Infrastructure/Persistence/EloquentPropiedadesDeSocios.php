<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Persistence;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Propiedades\PropiedadDelSocio;
use Maestros\Application\Propiedades\PropiedadesDeSocios;
use Maestros\Domain\Socios\CodigoDeSocio;

final class EloquentPropiedadesDeSocios implements PropiedadesDeSocios
{
    public function deSocio(CodigoDeSocio $socio): array
    {
        $filas = $this->activas()
            ->where('codigo_de_socio', $socio->value())
            ->orderBy('nombre')
            ->orderBy('id_propiedad')
            ->get(['id_propiedad', 'nombre']);

        return array_values(array_map(
            fn (object $fila): PropiedadDelSocio => new PropiedadDelSocio(
                $this->texto((array) $fila, 'id_propiedad'),
                $this->texto((array) $fila, 'nombre'),
            ),
            $filas->all(),
        ));
    }

    public function contarPorSocio(array $socios): array
    {
        if ($socios === []) {
            return [];
        }

        $filas = $this->activas()
            ->whereIn('codigo_de_socio', array_map(static fn (CodigoDeSocio $s): string => $s->value(), $socios))
            ->groupBy('codigo_de_socio')
            ->get(['codigo_de_socio', DB::raw('COUNT(*) as cantidad')]);

        $conteo = [];

        foreach ($filas as $fila) {
            $datos = (array) $fila;
            $cantidad = $datos['cantidad'] ?? 0;
            $conteo[$this->texto($datos, 'codigo_de_socio')] = is_numeric($cantidad) ? (int) $cantidad : 0;
        }

        return $conteo;
    }

    /** La única definición de «lo que la App puede ver». */
    private function activas(): Builder
    {
        $tabla = DB::getDriverName() === 'sqlsrv' ? 'maestros.propiedad' : 'maestros_propiedad';

        return DB::table($tabla)->where('activa', true);
    }

    /** @param array<string, mixed> $fila */
    private function texto(array $fila, string $clave): string
    {
        $valor = $fila[$clave] ?? '';

        return is_string($valor) || is_int($valor) ? (string) $valor : '';
    }
}
