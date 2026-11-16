<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Identidad\Domain\Dispositivos\TokenPush;

it('acepta un token de registro de Firebase y lo recorta', function () {
    $valor = 'dQw4w9WgXcQ:APA91bHPRgkF3JUikC4ENAHEeMrd41Zxv3hVZjC9KtT8OvPVGJ-hQMRKRrZuJAEcl7B338qju59zJMjw2DELjzEvxwYv7hH5Ynpc1ODQ0aT4U4OFEeBHWjp';

    expect(TokenPush::desde("  {$valor}  ")->valor())->toBe($valor);
});

it('rechaza un token vacio, con espacios o demasiado largo', function (string $valor) {
    TokenPush::desde($valor);
})->throws(DomainException::class)->with([
    'vacio' => [''],
    'solo espacios' => ['   '],
    'con un espacio' => ['con espacio'],
    'de 4001 caracteres, mas de lo que entra en un NVARCHAR de SQL Server' => [str_repeat('a', 4001)],
]);

it('acepta hasta 4000 caracteres, lo que entra en la columna de SQL Server', function () {
    expect(strlen(TokenPush::desde(str_repeat('a', 4000))->valor()))->toBe(4000);
});
