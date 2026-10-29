<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Maestros\Application\Eventos\BuzonDeEntrada;
use Maestros\Application\Eventos\MensajeRecibido;

/** Entrega los mensajes en orden y recuerda cuáles se completaron. */
final class BuzonEnMemoria implements BuzonDeEntrada
{
    /** @var list<MensajeRecibido> */
    public array $completados = [];

    public int $recibidos = 0;

    /** @var list<int|null> la espera de cada recibir, `null` la configurada */
    public array $esperas = [];

    /** @param list<MensajeRecibido> $pendientes */
    private function __construct(private array $pendientes) {}

    /** @param array<string, mixed> ...$sobres */
    public static function conSobres(array ...$sobres): self
    {
        return self::conCuerpos(...array_map(static fn (array $s): string => (string) json_encode($s), $sobres));
    }

    public static function conCuerpos(string ...$cuerpos): self
    {
        $mensajes = [];

        foreach (array_values($cuerpos) as $i => $cuerpo) {
            $mensajes[] = new MensajeRecibido($cuerpo, 'm-'.($i + 1), 1, 'bloqueo-'.($i + 1));
        }

        return new self($mensajes);
    }

    public function recibir(?int $esperaSegundos = null): ?MensajeRecibido
    {
        $this->recibidos++;
        $this->esperas[] = $esperaSegundos;

        return array_shift($this->pendientes);
    }

    public function completar(MensajeRecibido $mensaje): void
    {
        $this->completados[] = $mensaje;
    }
}
