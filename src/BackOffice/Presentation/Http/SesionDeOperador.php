<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use BackOffice\Domain\Operadores\Permiso;
use Illuminate\Support\Facades\Session;

/**
 * El operador vive en la sesión y no en una tabla: quién es lo dice Entra, y
 * lo único que este servicio guarda de él es el asiento de bitácora.
 */
final class SesionDeOperador
{
    private const CLAVE = 'backoffice.operador';

    public static function guardar(Operador $operador): void
    {
        Session::put(self::CLAVE, [
            'oid' => $operador->id->valor,
            'nombre' => $operador->nombre,
            'correo' => $operador->correo,
            'permisos' => array_map(static fn (Permiso $p): string => $p->value, $operador->permisos),
        ]);
    }

    public static function actual(): ?Operador
    {
        $datos = Session::get(self::CLAVE);

        if (! is_array($datos)) {
            return null;
        }

        $oid = $datos['oid'] ?? null;
        $nombre = $datos['nombre'] ?? null;
        $correo = $datos['correo'] ?? null;
        $permisos = $datos['permisos'] ?? null;

        // Una cookie de sesión vieja o manipulada no puede hacer estallar la
        // pantalla: si no trae las tres cosas, es como no tener sesión.
        if (! is_string($oid) || ! is_string($nombre) || ! is_string($correo) || trim($oid) === '') {
            return null;
        }

        // Una sesión de antes de que existieran los permisos se vuelve a
        // ingresar: suponer permisos que nadie le dio sería abrirle todo.
        if (! is_array($permisos)) {
            return null;
        }

        return new Operador(
            id: IdDeOperador::desdeOid($oid),
            nombre: $nombre,
            correo: $correo,
            // Un valor que ya no existe se descarta en vez de romper la sesión.
            permisos: array_values(array_filter(array_map(
                static fn (mixed $p): ?Permiso => is_string($p) ? Permiso::tryFrom($p) : null,
                $permisos,
            ))),
        );
    }

    public static function olvidar(): void
    {
        Session::forget(self::CLAVE);
    }
}
