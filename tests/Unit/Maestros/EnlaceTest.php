<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Productos\Enlace;

function mensajeDe(callable $crear): string
{
    try {
        $crear();
    } catch (DomainException $e) {
        return $e->getError()->description;
    }

    return '(no lanzo)';
}

it('acepta una imagen https', function () {
    expect(Enlace::imagen('https://cdn.x.bo/a/Foto.JPG')?->valor())->toBe('https://cdn.x.bo/a/Foto.JPG');
});

it('vacio quita el enlace', function () {
    expect(Enlace::imagen('   '))->toBeNull()
        ->and(Enlace::documento(''))->toBeNull();
});

it('normaliza lo que el contrato no admite', function () {
    // El formato uri del contrato no admite ® ni espacios: se codifican, y lo
    // que ya venía codificado no se codifica dos veces.
    expect(Enlace::documento('https://x.bo/2025/Aproach® Prima.pdf')?->valor())
        ->toBe('https://x.bo/2025/Aproach%C2%AE%20Prima.pdf')
        ->and(Enlace::documento('https://x.bo/2025/Aproach%C2%AE%20Prima.pdf')?->valor())
        ->toBe('https://x.bo/2025/Aproach%C2%AE%20Prima.pdf');
});

it('rechaza lo que no es https', function (string $texto) {
    expect(mensajeDe(fn () => Enlace::imagen($texto)))->toBe('Tiene que ser una dirección https completa.');
})->with(['http://x.bo/a.jpg', 'javascript:alert(1)', '/uploads/a.jpg', 'ftp://x.bo/a.jpg']);

it('una imagen no puede ser un pdf', function () {
    expect(mensajeDe(fn () => Enlace::imagen('https://x.bo/etiqueta.pdf')))
        ->toBe('La imagen tiene que ser .jpg, .jpeg, .png o .webp.');
});

it('un documento tiene que ser pdf', function () {
    expect(mensajeDe(fn () => Enlace::documento('https://x.bo/a.jpg')))->toBe('El documento tiene que ser un .pdf.');
});

it('rechaza lo que no es ascii fuera de la ruta', function () {
    expect(mensajeDe(fn () => Enlace::documento('https://x.bo/a.pdf?nombre=Ñandú')))
        ->toBe('Tiene que ser una dirección https completa.');
});

it('rechaza una direccion demasiado larga', function () {
    expect(mensajeDe(fn () => Enlace::documento('https://x.bo/'.str_repeat('a', 1000).'.pdf')))
        ->toBe('La dirección es demasiado larga (máximo 1000 caracteres).');
});
