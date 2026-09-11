<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

enum Plataforma: string
{
    case Android = 'android';
    case Ios = 'ios';
}
