<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\ColaDePush;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Sesiones\IdDeSesion;

/** Recuerda qué se encoló, para mirar a quién le llegaría cada push. */
final class ColaDePushEnMemoria implements ColaDePush
{
    /** @var list<array{sesion: string, aviso: AvisoParaPush}> */
    public array $encolados = [];

    public function encolar(IdDeSesion $sesion, AvisoParaPush $aviso): void
    {
        $this->encolados[] = ['sesion' => $sesion->value(), 'aviso' => $aviso];
    }

    /** @return list<string> los ids de sesión, ordenados */
    public function sesiones(): array
    {
        $ids = array_map(fn (array $e): string => $e['sesion'], $this->encolados);
        sort($ids);

        return $ids;
    }
}
