<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

final readonly class TokenEmitido
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiraEnSegundos,
    ) {}
}
