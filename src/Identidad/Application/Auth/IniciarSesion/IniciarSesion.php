<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\IniciarSesion;

use Core\Contracts\Request;
use Identidad\Domain\Desafios\IdDeDesafio;

final readonly class IniciarSesion implements Request
{
    public function __construct(
        public IdDeDesafio $idDeDesafio,
        public string $codigo,
        public ?string $instalacionId,
        public ?string $plataforma,
    ) {}
}
