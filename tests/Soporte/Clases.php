<?php

declare(strict_types=1);

namespace Tests\Soporte;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Recorre `src/` por el sistema de archivos y arma el FQCN desde el path
 * según PSR-4. No usa `get_declared_classes()` a propósito: eso solo ve lo ya
 * cargado, y con autoload perezoso es casi nada.
 */
final class Clases
{
    /** @var array<string, string> namespace raíz => subdirectorio de src/ */
    private const MODULOS = [
        'Core' => 'Core',
        'Maestros' => 'Maestros',
        'Identidad' => 'Identidad',
    ];

    /** @return list<class-string> */
    public static function deSrc(): array
    {
        $raiz = dirname(__DIR__, 2).'/src';
        $encontradas = [];

        foreach (self::MODULOS as $namespace => $carpeta) {
            $base = $raiz.'/'.$carpeta;

            if (! is_dir($base)) {
                continue;
            }

            /** @var iterable<SplFileInfo> $archivos */
            $archivos = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

            foreach ($archivos as $archivo) {
                if (! $archivo->isFile() || $archivo->getExtension() !== 'php') {
                    continue;
                }

                // No todo .php de src/ es una clase: los routes.php no lo son, y
                // preguntar por ellos los haría ejecutarse via el autoload.
                if (! self::declaraElTipo($archivo)) {
                    continue;
                }

                $relativo = substr($archivo->getPathname(), strlen($base) + 1);
                $sinExtension = substr($relativo, 0, -4);
                $clase = $namespace.'\\'.str_replace(['/', '\\'], '\\', $sinExtension);

                if (class_exists($clase) || interface_exists($clase)) {
                    $encontradas[] = $clase;
                }
            }
        }

        return $encontradas;
    }

    /** ¿El archivo declara un tipo que se llama igual que él, como manda PSR-4? */
    private static function declaraElTipo(SplFileInfo $archivo): bool
    {
        $nombre = preg_quote($archivo->getBasename('.php'), '/');
        $contenido = (string) file_get_contents($archivo->getPathname());

        return preg_match(
            '/^(?:final |abstract |readonly )*(?:class|interface|trait|enum)\s+'.$nombre.'\b/m',
            $contenido,
        ) === 1;
    }
}
