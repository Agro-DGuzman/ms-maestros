<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\ObtenerContexto;

/**
 * La misma proyección que devuelven `GET /mi-cuenta` y el `contexto` del
 * login. Dos formas de armarla serían dos formas de que se desincronicen.
 */
final readonly class ContextoDeContacto
{
    /** @param list<array{cardCode: string, razonSocial: string, iniciales: string, cantidadPropiedades: int}> $socios */
    public function __construct(
        public string $nombre,
        public string $iniciales,
        public string $celular,
        public string $grupoId,
        public string $grupoNombre,
        public array $socios,
    ) {}

    /** @return array<string, mixed> */
    public function aArray(): array
    {
        return [
            'nombre' => $this->nombre,
            'iniciales' => $this->iniciales,
            'celular' => $this->celular,
            'grupoEconomico' => [
                'id' => $this->grupoId,
                'nombre' => $this->grupoNombre,
                'socios' => $this->socios,
            ],
        ];
    }
}
