<?php

declare(strict_types=1);

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * El ingress de Container Apps atiende HTTPS y le pasa a la app HTTP plano.
 * Todas las URL que la app arma —la acción de cada formulario, cada
 * redirección— salen del esquema del pedido, así que sin esto apuntan a
 * `http://`: el navegador avisa que el formulario no es seguro, el ingress
 * redirige a HTTPS y en esa redirección el POST llega como GET.
 */
beforeEach(function () {
    Route::get('/prueba-de-esquema', fn (Request $pedido): string => url('/admin/contactos'));
});

function pedirUrl(array $cabeceras = []): string
{
    return (string) test()->get('/prueba-de-esquema', $cabeceras)->getContent();
}

it('arma las url con https cuando el ingress dice que el pedido llego por https', function () {
    config(['app.detras_de_proxy' => true]);

    expect(pedirUrl(['X-Forwarded-Proto' => 'https']))->toStartWith('https://');
});

it('sigue en http si el ingress dice que llego por http', function () {
    config(['app.detras_de_proxy' => true]);

    expect(pedirUrl(['X-Forwarded-Proto' => 'http']))->toStartWith('http://');
});

it('sin proxy adelante no le cree a la cabecera', function () {
    config(['app.detras_de_proxy' => false]);

    expect(pedirUrl(['X-Forwarded-Proto' => 'https']))->toStartWith('http://');
});

it('el formulario de ingreso envia por https', function () {
    config([
        'app.detras_de_proxy' => true,
        'backoffice.autenticador' => 'contrasena',
        'backoffice.contrasena.operadores' => 'ana@agropartners.com.bo|'
            .password_hash('Clave-De-Ana-1', PASSWORD_BCRYPT, ['cost' => 4]).'|Ana Suárez',
    ]);
    app()->forgetInstance(AutenticadorDeOperador::class);

    $this->get('/admin/formulario', ['X-Forwarded-Proto' => 'https'])
        ->assertOk()
        ->assertSee('action="https://', escape: false);
});
