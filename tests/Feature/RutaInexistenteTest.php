<?php

declare(strict_types=1);

it('una ruta inexistente responde el envelope en JSON aunque no se pida JSON', function (string $ruta) {
    // El Sincronizador no manda Accept. Con la página HTML de Laravel, su
    // registro guardaba el comienzo de un documento en lugar de la ruta que
    // de verdad había llegado.
    $this->post($ruta)
        ->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'data' => null,
            'success' => false,
            'error' => [
                'code' => ['RUTA_INEXISTENTE'],
                'description' => "No existe la ruta POST {$ruta}",
                'structuredMessage' => [],
                'type' => 'NOT_FOUND',
            ],
        ]);
})->with(['/productos', '/v1/no-existe', '/ingesta/v1/no-existe']);

it('un metodo que la ruta no admite responde 405 METODO_NO_PERMITIDO con los que admite', function () {
    $this->get('/ingesta/v1/productos')
        ->assertStatus(405)
        ->assertHeader('Content-Type', 'application/json')
        ->assertHeader('Allow', 'POST')
        ->assertJsonPath('error.code', ['METODO_NO_PERMITIDO'])
        ->assertJsonPath('error.description', 'La ruta /ingesta/v1/productos no admite GET');
});

it('el back-office sigue respondiendo su pagina de error', function () {
    // Lo mira una persona en el navegador, no un programa.
    $respuesta = $this->get('/admin/no-existe')->assertStatus(404);

    expect($respuesta->headers->get('Content-Type'))->toStartWith('text/html');
});

it('las rutas que Laravel trae de serie no existen', function (string $metodo, string $ruta) {
    // La bienvenida de Laravel y el disco local servido por HTTP no son de
    // este servicio: lo que no usa nadie tampoco tiene que estar expuesto.
    $this->call($metodo, $ruta)
        ->assertStatus(404)
        ->assertJsonPath('error.code', ['RUTA_INEXISTENTE'])
        ->assertJsonPath('error.description', "No existe la ruta {$metodo} {$ruta}");
})->with([
    ['GET', '/'],
    ['GET', '/storage/archivo.txt'],
    ['PUT', '/storage/archivo.txt'],
]);
