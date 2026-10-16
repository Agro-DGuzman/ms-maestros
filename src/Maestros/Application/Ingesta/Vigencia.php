<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta;

use DateTimeImmutable;

/**
 * La regla de la que cuelga que una réplica nunca retroceda, compartida por
 * socios y productos: lo guardado está truncado al segundo, así que el cuerpo
 * se compara igual, y empatar es no reemplazar. Sin eso, un envío viejo dentro
 * del mismo segundo parecería más nuevo.
 */
final class Vigencia
{
    public static function esMasNuevo(DateTimeImmutable $cuerpo, DateTimeImmutable $guardado): bool
    {
        return $cuerpo->getTimestamp() > $guardado->getTimestamp();
    }
}
