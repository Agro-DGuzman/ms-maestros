<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Identidad\Domain\Push\PreferenciasDePush;
use Identidad\Domain\Push\PreferenciasDePushRepository;
use Illuminate\Support\Facades\DB;
use Maestros\Domain\Contactos\IdDePersona;

final class EloquentPreferenciasDePushRepository implements PreferenciasDePushRepository
{
    public function de(IdDePersona $persona): PreferenciasDePush
    {
        $fila = DB::table($this->tabla())->where('id_de_persona', $persona->value())->first();

        if ($fila === null) {
            return PreferenciasDePush::todasPrendidas($persona);
        }

        $columnas = (array) $fila;

        return PreferenciasDePush::de(
            $persona,
            (bool) $columnas['facturas'],
            (bool) $columnas['pagos'],
            (bool) $columnas['reportes'],
            (bool) $columnas['catalogo'],
            self::vigencia($columnas['vigente_desde'] ?? null) ?? new DateTimeImmutable('@0'),
        );
    }

    public function guardarSiEsMasNueva(PreferenciasDePush $preferencias): bool
    {
        $persona = $preferencias->persona()->value();
        $vigente = $preferencias->vigenteDesde();
        $fila = DB::table($this->tabla())->where('id_de_persona', $persona)->first(['vigente_desde']);
        $guardada = $fila === null ? null : self::vigencia(((array) $fila)['vigente_desde'] ?? null);

        if ($vigente === null || ($guardada !== null && $vigente <= $guardada)) {
            return false;
        }

        $valores = [
            'facturas' => $preferencias->facturas(),
            'pagos' => $preferencias->pagos(),
            'reportes' => $preferencias->reportes(),
            'catalogo' => $preferencias->catalogo(),
            // Con microsegundos y en UTC: dos cambios en el mismo segundo tienen que ordenarse.
            'vigente_desde' => $vigente->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ];

        if ($fila === null) {
            DB::table($this->tabla())->insert(['id_de_persona' => $persona] + $valores);
        } else {
            DB::table($this->tabla())->where('id_de_persona', $persona)->update($valores);
        }

        return true;
    }

    /** SQL Server la devuelve con siete decimales y, si es datetimeoffset, con desfase. */
    private static function vigencia(mixed $valor): ?DateTimeImmutable
    {
        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        $recortado = preg_replace('/(\.\d{6})\d+/', '$1', trim($valor)) ?? $valor;

        return new DateTimeImmutable($recortado, new DateTimeZone('UTC'));
    }

    private function tabla(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'identidad.preferencias_de_push' : 'identidad_preferencias_de_push';
    }
}
