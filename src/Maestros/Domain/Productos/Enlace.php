<?php

declare(strict_types=1);

namespace Maestros\Domain\Productos;

use Core\Results\DomainException;
use Core\Results\Error;

/**
 * Una dirección del catálogo que no viene de SAP: la imagen del producto o uno
 * de sus documentos. La pega una persona, así que se valida y se normaliza
 * antes de que la App la reciba.
 *
 * Imagen y documento se distinguen por la extensión: pegar el PDF de una
 * etiqueta como foto es justo el error que ya apareció en la web.
 */
final readonly class Enlace
{
    private const int MAXIMO = 1000;

    private const array EXTENSIONES_DE_IMAGEN = ['jpg', 'jpeg', 'png', 'webp'];

    private function __construct(private string $valor) {}

    /** Null si el texto está vacío: vaciar el campo quita el enlace. */
    public static function imagen(string $texto): ?self
    {
        return self::desde($texto, self::EXTENSIONES_DE_IMAGEN, 'La imagen tiene que ser .jpg, .jpeg, .png o .webp.');
    }

    /** Null si el texto está vacío: vaciar el campo quita el documento. */
    public static function documento(string $texto): ?self
    {
        return self::desde($texto, ['pdf'], 'El documento tiene que ser un .pdf.');
    }

    public function valor(): string
    {
        return $this->valor;
    }

    /** @param list<string> $extensiones */
    private static function desde(string $texto, array $extensiones, string $siNoEsDelTipo): ?self
    {
        $texto = trim($texto);

        if ($texto === '') {
            return null;
        }

        $partes = parse_url($texto);

        // Solo https: la App la abre directo, y un `javascript:` o una ruta
        // relativa no son una dirección que se pueda abrir.
        if ($partes === false || ($partes['scheme'] ?? null) !== 'https' || ($partes['host'] ?? '') === '') {
            throw self::invalido('Tiene que ser una dirección https completa.');
        }

        $ruta = $partes['path'] ?? '';
        $normalizada = self::normalizarRuta($ruta);
        $valor = 'https://'.$partes['host']
            .(isset($partes['port']) ? ':'.$partes['port'] : '')
            .$normalizada
            .(isset($partes['query']) ? '?'.$partes['query'] : '')
            .(isset($partes['fragment']) ? '#'.$partes['fragment'] : '');

        // Lo que queda sin codificar fuera de la ruta no es un uri válido.
        if (preg_match('/[^\x21-\x7E]/', $valor) === 1) {
            throw self::invalido('Tiene que ser una dirección https completa.');
        }

        if (strlen($valor) > self::MAXIMO) {
            throw self::invalido('La dirección es demasiado larga (máximo 1000 caracteres).');
        }

        $extension = strtolower(pathinfo(rawurldecode($ruta), PATHINFO_EXTENSION));

        if (! in_array($extension, $extensiones, true)) {
            throw self::invalido($siNoEsDelTipo);
        }

        return new self($valor);
    }

    /**
     * Cada segmento se decodifica y se vuelve a codificar: `®` y los espacios
     * pasan a `%C2%AE` y `%20`, y lo que ya venía codificado queda igual.
     */
    private static function normalizarRuta(string $ruta): string
    {
        return implode('/', array_map(
            static fn (string $segmento): string => rawurlencode(rawurldecode($segmento)),
            explode('/', $ruta),
        ));
    }

    private static function invalido(string $mensaje): DomainException
    {
        return new DomainException(Error::validation('ENLACE_INVALIDO', $mensaje));
    }
}
