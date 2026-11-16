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
    'de 4097 caracteres' => [str_repeat('a', 4097)],
]);
