<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
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

        // Una cookie de sesión vieja o manipulada no puede hacer estallar la
        // pantalla: si no trae las tres cosas, es como no tener sesión.
        if (! is_string($oid) || ! is_string($nombre) || ! is_string($correo) || trim($oid) === '') {
            return null;
        }

        return new Operador(
            id: IdDeOperador::desdeOid($oid),
            nombre: $nombre,
            correo: $correo,
        );
    }

    public static function olvidar(): void
    {
        Session::forget(self::CLAVE);
    }
}
