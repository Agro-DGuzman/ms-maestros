<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Persistence;

use DateTimeImmutable;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Illuminate\Contracts\Encryption\Encrypter;
use Maestros\Domain\Contactos\IdDePersona;

/** La clave de cifrado viene de Key Vault por `APP_KEY` en despliegue. */
final readonly class BovedaCifrada implements BovedaDeContrasenas
{
    public function __construct(private Encrypter $cifrador) {}

    public function guardar(IdDePersona $persona, string $contrasena): void
    {
        CredencialRecord::query()->updateOrCreate(
            ['id_de_persona' => $persona->value()],
            [
                'id_de_persona' => $persona->value(),
                'contrasena_cifrada' => $this->cifrador->encryptString($contrasena),
                'rotada_el' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function leer(IdDePersona $persona): ?string
    {
        $record = CredencialRecord::query()->find($persona->value());

        return $record === null ? null : $this->cifrador->decryptString((string) $record->contrasena_cifrada);
    }
}
