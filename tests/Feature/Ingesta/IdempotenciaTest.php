<?php

declare(strict_types=1);

use App\Persistence\RespuestasDeIngesta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Tests\Soporte\TokenDeEntra;

uses(RefreshDatabase::class);

beforeEach(function () {
    TokenDeEntra::configurar();
    $this->ejecuciones = 0;
    $this->statusDeLaRuta = 201;

    $ruta = function () {
        $this->ejecuciones++;

        return new JsonResponse(['data' => ['cardCode' => 'X'.$this->ejecuciones], 'success' => true, 'error' => null], $this->statusDeLaRuta);
    };

    foreach (['post', 'put'] as $metodo) {
        Route::{$metodo}('/ingesta/v1/__prueba/{cardCode}', $ruta)
            ->middleware(['gateway:ingesta', 'ingesta.token', 'ingesta.idempotencia']);
    }
});

function conClave(?string $clave): array
{
    $cabeceras = ['X-Gateway-Secret' => 'secreto-del-gateway', 'Authorization' => 'Bearer '.TokenDeEntra::valido()];

    return $clave === null ? $cabeceras : $cabeceras + ['Idempotency-Key' => $clave];
}

it('sin Idempotency-Key, o con una que no son 32 hex, responde 400 con el campo', function (?string $clave) {
    test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave))
        ->assertStatus(400)
        ->assertJsonPath('error.structuredMessage.0.campo', 'Idempotency-Key');
})->with(['sin clave' => [null], 'corta' => ['abc'], 'mayusculas' => [str_repeat('A', 32)]]);

it('la misma terna devuelve lo mismo sin volver a ejecutar', function () {
    $clave = str_repeat('a', 32);

    $primera = test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave))->assertStatus(201);
    $segunda = test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave))->assertStatus(201);

    expect($this->ejecuciones)->toBe(1)
        ->and($segunda->getContent())->toBe($primera->getContent());
});

it('la misma clave con otro metodo u otra ruta se ejecuta', function () {
    // El Sincronizador reintenta un POST que dio 409 como PUT con la misma
    // clave: con la clave sola, el PUT recibiría el 409 guardado.
    $clave = str_repeat('a', 32);

    test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave));
    test()->putJson('/ingesta/v1/__prueba/C-1', [], conClave($clave));
    test()->postJson('/ingesta/v1/__prueba/C-2', [], conClave($clave));

    expect($this->ejecuciones)->toBe(3);
});

it('una respuesta 5xx no se guarda y la repeticion se vuelve a ejecutar', function () {
    $this->statusDeLaRuta = 500;
    $clave = str_repeat('a', 32);

    test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave))->assertStatus(500);

    $this->statusDeLaRuta = 201;

    test()->postJson('/ingesta/v1/__prueba/C-1', [], conClave($clave))->assertStatus(201);

    expect($this->ejecuciones)->toBe(2);
});

it('guardar dos veces la misma terna no explota', function () {
    // Dos entregas casi simultáneas: la segunda no puede tirar un 500 por la
    // clave primaria.
    $respuestas = app(RespuestasDeIngesta::class);

    $respuestas->guardar(str_repeat('a', 32), 'POST', '/ingesta/v1/socios', 201, '{"a":1}');
    $respuestas->guardar(str_repeat('a', 32), 'POST', '/ingesta/v1/socios', 409, '{"a":2}');

    expect($respuestas->buscar(str_repeat('a', 32), 'POST', '/ingesta/v1/socios'))->toBe(['status' => 201, 'cuerpo' => '{"a":1}']);
});
