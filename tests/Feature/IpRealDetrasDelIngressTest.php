<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * De `ip()` cuelgan tres cosas: el filtro de rangos del back-office, el freno
 * de intentos del ingreso, y la dirección que la bitácora guarda para siempre.
 * Por eso se prueba el contrato en sí y no cada consumidor por separado.
 */
beforeEach(function () {
    Route::get('/prueba-de-ip', fn (Request $pedido): string => (string) $pedido->ip());
});

function pedirIp(array $cabeceras = []): string
{
    return test()->withServerVariables(['REMOTE_ADDR' => '100.100.0.5'])
        ->get('/prueba-de-ip', $cabeceras)
        ->getContent();
}

it('devuelve la ip que el ingress agrego al final', function () {
    config(['app.detras_de_proxy' => true]);

    expect(pedirIp(['X-Forwarded-For' => '190.129.4.7']))->toBe('190.129.4.7');
});

it('ignora lo que el cliente haya escrito antes', function () {
    // Container Apps agrega su lectura al final; todo lo anterior lo puso quien
    // hizo el pedido y no vale nada.
    config(['app.detras_de_proxy' => true]);

    expect(pedirIp(['X-Forwarded-For' => '1.2.3.4, 8.8.8.8, 190.129.4.7']))->toBe('190.129.4.7');
});

it('sin proxy adelante no mira la cabecera', function () {
    // Apagado es el valor por defecto, y es lo correcto: sin un proxy que la
    // reescriba, esa cabecera la controla quien conecta.
    config(['app.detras_de_proxy' => false]);

    expect(pedirIp(['X-Forwarded-For' => '190.129.4.7']))->toBe('100.100.0.5');
});

it('sin cabecera se queda con quien conecta', function () {
    config(['app.detras_de_proxy' => true]);

    expect(pedirIp())->toBe('100.100.0.5');
});
