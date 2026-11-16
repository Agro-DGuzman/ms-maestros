<?php

declare(strict_types=1);

namespace Identidad\Domain\Push;

use Maestros\Domain\Contactos\IdDePersona;

interface PreferenciasDePushRepository
{
    /** Sin copia guardada, todas prendidas. */
    public function de(IdDePersona $persona): PreferenciasDePush;

    /**
     * Una foto vieja no pisa una nueva, y una igual cuenta como vieja, con la
     * misma regla que las propiedades (NP8).
     *
     * @return bool si se guardó
     */
    public function guardarSiEsMasNueva(PreferenciasDePush $preferencias): bool;
}
