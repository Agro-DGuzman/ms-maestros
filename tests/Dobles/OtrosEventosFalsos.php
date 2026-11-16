<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Maestros\Application\Eventos\Sobre;
use Maestros\Application\Ingesta\ProcesarEvento\OtrosEventos;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;

/** Atiende los tipos que se le dan, guarda cada sobre y devuelve el resultado configurado. */
final class OtrosEventosFalsos implements OtrosEventos
{
    /** @var list<Sobre> */
    public array $atendidos = [];

    /** @param array<string, ResultadoDelEvento> $resultados por tipo */
    public function __construct(private readonly array $resultados) {}

    public function atiende(string $tipo): bool
    {
        return isset($this->resultados[$tipo]);
    }

    public function atender(Sobre $sobre): ResultadoDelEvento
    {
        $this->atendidos[] = $sobre;

        return $this->resultados[$sobre->tipo];
    }
}
