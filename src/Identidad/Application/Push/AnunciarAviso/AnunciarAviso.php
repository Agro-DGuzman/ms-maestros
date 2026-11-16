<?php

declare(strict_types=1);

namespace Identidad\Application\Push\AnunciarAviso;

use Core\Contracts\Request;
use Core\Contracts\RequiereTransaccion;
use Identidad\Domain\Push\AvisoParaPush;

/** Un aviso nuevo de la bandeja: se encola un push por cada teléfono que lo puede ver. */
final readonly class AnunciarAviso implements Request, RequiereTransaccion
{
    public function __construct(public AvisoParaPush $aviso) {}
}
