<?php

declare(strict_types=1);

namespace App\Eventos;

use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use LogicException;
use Maestros\Application\Eventos\EventoInvalido;
use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\OtrosEventos;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;

/**
 * Los tipos del bus que atiende Identidad. Este es el único lugar donde se
 * registran: traduce el sobre a una petición de Identidad y la despacha por
 * el mediator, así Maestros no la nombra e Identidad no conoce el sobre.
 */
final readonly class EventosDeIdentidad implements OtrosEventos
{
    private const array TIPOS = [TraductorDePreferencias::TIPO];

    public function __construct(private Mediator $mediator) {}

    public function atiende(string $tipo): bool
    {
        return in_array($tipo, self::TIPOS, true);
    }

    public function atender(Sobre $sobre): ResultadoDelEvento
    {
        return match ($sobre->tipo) {
            TraductorDePreferencias::TIPO => $this->foto($this->mediator->send(TraductorDePreferencias::traducir($sobre))),
            default => throw EventoInvalido::sinTraductor($sobre->tipo),
        };
    }

    /** Una foto se aplica o se ignora por vieja. */
    private function foto(mixed $resultado): ResultadoDelEvento
    {
        if (! $resultado instanceof ResultWithValue || $resultado->isFailure()) {
            throw new LogicException('Identidad no pudo registrar el evento');
        }

        return $resultado->value() === true ? ResultadoDelEvento::Aplicado : ResultadoDelEvento::IgnoradoPorViejo;
    }
}
