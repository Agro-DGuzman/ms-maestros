<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Contrasena;

use RuntimeException;

/**
 * Un operador del piloto: los datos que en Entra vendrían en el token, acá
 * puestos a mano en configuración.
 */
final readonly class OperadorConContrasena
{
    public function __construct(
        public string $correo,
        public string $hash,
        public string $nombre,
    ) {}

    /**
     * Arma la entrada que `listaDesde()` va a leer. Las dos direcciones del
     * formato viven acá para que no puedan separarse.
     */
    public static function registroPara(string $correo, string $contrasena, string $nombre): string
    {
        return implode('|', [
            trim($correo),
            password_hash($contrasena, PASSWORD_BCRYPT),
            trim($nombre),
        ]);
    }

    /**
     * Lee `correo|hash|Nombre` separados por `;`.
     *
     * Van todos en una sola variable de entorno en vez de tres por persona
     * porque así sumar a alguien no obliga a cambiar la configuración de la
     * aplicación. Ni `|` ni `;` aparecen en un hash bcrypt, cuyo alfabeto es
     * `./A-Za-z0-9` más `$`, así que no pueden chocar con lo que separan.
     *
     * @return list<self>
     */
    public static function listaDesde(string $empaquetado): array
    {
        $operadores = [];

        foreach (explode(';', $empaquetado) as $registro) {
            $registro = trim($registro);

            if ($registro === '') {
                continue;
            }

            $campos = explode('|', $registro);

            // Saltear una entrada mal formada dejaría a esa persona sin poder
            // entrar y sin decir por qué.
            if (count($campos) !== 3) {
                throw new RuntimeException('BACKOFFICE_OPERADOR_MAL_FORMADO');
            }

            [$correo, $hash, $nombre] = array_map('trim', $campos);

            if ($correo === '' || $hash === '' || $nombre === '') {
                throw new RuntimeException('BACKOFFICE_OPERADOR_MAL_FORMADO');
            }

            $operadores[] = new self(correo: $correo, hash: $hash, nombre: $nombre);
        }

        return $operadores;
    }
}
