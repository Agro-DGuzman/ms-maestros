<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

final readonly class EnlacesDeProducto
{
    public function __construct(
        public ?string $imagen,
        public ?string $fichaTecnica,
        public ?string $hojaDeSeguridad,
        public ?string $registroSanitario,
    ) {}

    public function valor(CampoDeEnlace $campo): ?string
    {
        return match ($campo) {
            CampoDeEnlace::Imagen => $this->imagen,
            CampoDeEnlace::FichaTecnica => $this->fichaTecnica,
            CampoDeEnlace::HojaDeSeguridad => $this->hojaDeSeguridad,
            CampoDeEnlace::RegistroSanitario => $this->registroSanitario,
        };
    }

    public function documentosCargados(): int
    {
        return count(array_filter([$this->fichaTecnica, $this->hojaDeSeguridad, $this->registroSanitario]));
    }
}
