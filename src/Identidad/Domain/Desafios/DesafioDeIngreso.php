<?php

declare(strict_types=1);

namespace Identidad\Domain\Desafios;

use Core\Domain\AggregateRoot;
use Core\Results\DomainException;
use Core\Results\Result;
use DateTimeImmutable;
use Maestros\Domain\Contactos\Celular;

/**
 * El código de cuatro dígitos enviado por WhatsApp y el plazo durante el cual
 * sigue siendo válido. Se consume al primer código correcto.
 */
final class DesafioDeIngreso extends AggregateRoot
{
    public const VIGENCIA_EN_MINUTOS = 5;

    public const INTENTOS_MAXIMOS = 5;

    private function __construct(
        IdDeDesafio $id,
        private readonly Celular $celular,
        private readonly string $digitos,
        private readonly DateTimeImmutable $expiraEn,
        private int $intentosFallidos,
        private bool $consumido,
    ) {
        parent::__construct($id);
    }

    public static function emitir(
        IdDeDesafio $id,
        Celular $celular,
        string $digitos,
        DateTimeImmutable $ahora,
    ): self {
        if (preg_match('/^\d{4}$/', $digitos) !== 1) {
            throw new DomainException(DesafioErrors::digitosInvalidos());
        }

        return new self(
            $id,
            $celular,
            $digitos,
            $ahora->modify('+'.self::VIGENCIA_EN_MINUTOS.' minutes'),
            0,
            false,
        );
    }

    public static function reconstituir(
        IdDeDesafio $id,
        Celular $celular,
        string $digitos,
        DateTimeImmutable $expiraEn,
        int $intentosFallidos,
        bool $consumido,
    ): self {
        return new self($id, $celular, $digitos, $expiraEn, $intentosFallidos, $consumido);
    }

    public function resolver(string $codigo, DateTimeImmutable $ahora): Result
    {
        if ($this->consumido) {
            return Result::failure(DesafioErrors::codigoInvalido());
        }

        if ($ahora > $this->expiraEn) {
            return Result::failure(DesafioErrors::codigoInvalido());
        }

        if ($this->intentosFallidos >= self::INTENTOS_MAXIMOS) {
            return Result::failure(DesafioErrors::codigoInvalido());
        }

        if (! hash_equals($this->digitos, $codigo)) {
            $this->intentosFallidos++;

            return Result::failure(DesafioErrors::codigoInvalido());
        }

        $this->consumido = true;

        return Result::success();
    }

    public function idDeDesafio(): IdDeDesafio
    {
        $id = $this->id();
        assert($id instanceof IdDeDesafio);

        return $id;
    }

    public function celular(): Celular
    {
        return $this->celular;
    }

    public function digitos(): string
    {
        return $this->digitos;
    }

    public function expiraEn(): DateTimeImmutable
    {
        return $this->expiraEn;
    }

    public function intentosFallidos(): int
    {
        return $this->intentosFallidos;
    }

    public function estaConsumido(): bool
    {
        return $this->consumido;
    }
}
