<?php

declare(strict_types=1);

namespace Identidad\Domain\Push;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Un aviso de la bandeja de ms-comercial, con lo que hace falta para mandarlo
 * por push: el texto es el de la bandeja, escrito por un solo servicio (NP9).
 */
final readonly class AvisoParaPush
{
    /** Las fechas de los documentos son de Bolivia: «hoy» y «ayer» se cuentan allá. */
    private const string ZONA_DEL_DOCUMENTO = 'America/La_Paz';

    public function __construct(
        public string $avisoId,
        public CategoriaDeAviso $categoria,
        public ?string $cardCode,
        public string $titulo,
        public string $cuerpo,
        public DateTimeImmutable $creadoEn,
        public ?string $fechaDelDocumento,
    ) {}

    /**
     * NP13: un aviso viejo queda solo en la bandeja. Las 24 horas desde la
     * lectura frenan un bus o un Firebase caídos; la fecha del documento frena
     * un atraso del Sincronizador o una carga masiva, cuyos avisos nacen con
     * la hora de la lectura.
     */
    public function esViejo(DateTimeImmutable $ahora): bool
    {
        if ($this->creadoEn < $ahora->modify('-24 hours')) {
            return true;
        }

        if ($this->fechaDelDocumento === null) {
            return false;
        }

        $zona = new DateTimeZone(self::ZONA_DEL_DOCUMENTO);
        $hoy = new DateTimeImmutable($ahora->setTimezone($zona)->format('Y-m-d'), $zona);

        // Las dos son YYYY-MM-DD: comparar el texto es comparar las fechas.
        return $this->fechaDelDocumento < $hoy->modify('-1 day')->format('Y-m-d');
    }

    /** @return array{avisoId: string, categoria: string, cardCode: string|null, titulo: string, cuerpo: string, creadoEn: string, fechaDelDocumento: string|null} */
    public function aArreglo(): array
    {
        return [
            'avisoId' => $this->avisoId,
            'categoria' => $this->categoria->value,
            'cardCode' => $this->cardCode,
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'creadoEn' => $this->creadoEn->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z'),
            'fechaDelDocumento' => $this->fechaDelDocumento,
        ];
    }

    /** @param array<array-key, mixed> $arreglo lo que dejó aArreglo() en el trabajo */
    public static function desdeArreglo(array $arreglo): self
    {
        $texto = static function (string $clave) use ($arreglo): string {
            $valor = $arreglo[$clave] ?? null;

            if (! is_string($valor)) {
                throw new InvalidArgumentException("El aviso del trabajo no trae {$clave}");
            }

            return $valor;
        };
        $opcional = static fn (string $clave): ?string => is_string($arreglo[$clave] ?? null) ? $arreglo[$clave] : null;

        return new self(
            $texto('avisoId'),
            CategoriaDeAviso::from($texto('categoria')),
            $opcional('cardCode'),
            $texto('titulo'),
            $texto('cuerpo'),
            new DateTimeImmutable($texto('creadoEn')),
            $opcional('fechaDelDocumento'),
        );
    }
}
