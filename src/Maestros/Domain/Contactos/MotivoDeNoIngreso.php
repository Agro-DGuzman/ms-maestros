<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

/** Por qué un número no puede entrar a la App, para decírselo al operador. */
enum MotivoDeNoIngreso: string
{
    case SinContactos = 'sin-contactos';
    case SoloSinGrupo = 'solo-sin-grupo';
    case VariosGrupos = 'varios-grupos';
}
