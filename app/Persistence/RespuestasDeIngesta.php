<?php

declare(strict_types=1);

namespace App\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** La memoria de la idempotencia de /ingesta: status y cuerpo de la primera vez. */
final class RespuestasDeIngesta
{
    /** @return array{status: int, cuerpo: ?string}|null */
    public function buscar(string $clave, string $metodo, string $ruta): ?array
    {
        $fila = DB::table($this->tabla())
            ->where('clave', $clave)
            ->where('metodo', $metodo)
            ->where('ruta', $ruta)
            ->first(['status', 'cuerpo']);

        if ($fila === null) {
            return null;
        }

        $datos = (array) $fila;
        $status = $datos['status'] ?? null;
        $cuerpo = $datos['cuerpo'] ?? null;

        return [
            'status' => is_numeric($status) ? (int) $status : 500,
            'cuerpo' => is_string($cuerpo) ? $cuerpo : null,
        ];
    }

    /**
     * `insertOrIgnore`: dos entregas casi simultáneas de la misma terna no
     * pueden convertirse en un 500 por la clave primaria. Gana la primera.
     */
    public function guardar(string $clave, string $metodo, string $ruta, int $status, ?string $cuerpo): void
    {
        DB::table($this->tabla())->insertOrIgnore([
            'clave' => $clave,
            'metodo' => $metodo,
            'ruta' => $ruta,
            'status' => $status,
            'cuerpo' => $cuerpo,
            'recibida_el' => (new DateTimeImmutable)->format('Y-m-d H:i:s'),
        ]);
    }

    private function tabla(): string
    {
        return DB::getDriverName() === 'sqlsrv' ? 'maestros.ingesta_respuestas' : 'maestros_ingesta_respuestas';
    }
}
