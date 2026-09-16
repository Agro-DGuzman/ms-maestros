<?php

declare(strict_types=1);

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Presentation\Http\SesionDeOperador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

const CLAVE_DE_ANA = 'Clave-De-Ana-1';

/**
 * Deja el back-office configurado con el autenticador de contraseña y dos
 * operadores, como va a estar el piloto.
 */
function conOperadoresDeContrasena(): void
{
    $hash = fn (string $clave): string => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 4]);

    config([
        'backoffice.autenticador' => 'contrasena',
        'backoffice.contrasena.operadores' => implode(';', [
            'ana@agropartners.com.bo|'.$hash(CLAVE_DE_ANA).'|Ana Suárez',
            'bruno@agropartners.com.bo|'.$hash('Clave-De-Bruno-2').'|Bruno Ortiz',
        ]),
    ]);

    // El autenticador es singleton: si algo lo resolvió con la configuración
    // anterior, hay que soltarlo para que se arme con esta.
    app()->forgetInstance(AutenticadorDeOperador::class);
}

/** Recorre el ingreso completo y devuelve la respuesta del callback. */
function ingresarComo(string $correo, string $contrasena): TestResponse
{
    test()->get('/admin/entrar');

    return test()->post('/admin/verificar', ['correo' => $correo, 'contrasena' => $contrasena]);
}

it('entrar lleva al formulario propio', function () {
    conOperadoresDeContrasena();

    $this->get('/admin/entrar')->assertRedirect(route('admin.formulario'));
});

it('el formulario pide correo y contrasena', function () {
    conOperadoresDeContrasena();

    $this->get('/admin/formulario')
        ->assertOk()
        ->assertSee('name="correo"', escape: false)
        ->assertSee('name="contrasena"', escape: false);
});

it('la contrasena correcta abre la sesion del operador que entro', function () {
    conOperadoresDeContrasena();

    $aCallback = ingresarComo('bruno@agropartners.com.bo', 'Clave-De-Bruno-2');
    $aCallback->assertRedirectContains('/admin/callback');

    $this->get($aCallback->headers->get('Location'))->assertRedirect(route('admin.contactos'));

    expect(SesionDeOperador::actual())->not->toBeNull()
        ->and(SesionDeOperador::actual()->nombre)->toBe('Bruno Ortiz')
        ->and(SesionDeOperador::actual()->correo)->toBe('bruno@agropartners.com.bo');
});

it('la contrasena equivocada no abre sesion', function () {
    conOperadoresDeContrasena();

    ingresarComo('ana@agropartners.com.bo', 'otra-clave')
        ->assertRedirect(route('admin.formulario'))
        ->assertSessionHas('error', 'Correo o contraseña incorrectos.');

    expect(SesionDeOperador::actual())->toBeNull();
});

it('un correo que no es de nadie dice exactamente lo mismo', function () {
    conOperadoresDeContrasena();

    ingresarComo('nadie@agropartners.com.bo', CLAVE_DE_ANA)
        ->assertSessionHas('error', 'Correo o contraseña incorrectos.');

    expect(SesionDeOperador::actual())->toBeNull();
});

it('frena al sexto intento aunque la contrasena sea la correcta', function () {
    // Cinco intentos, como el desafío de ingreso. La contraseña correcta al
    // final es lo que hace honesta la prueba: si el freno no existiera, este
    // sexto intento entraría, y el test tiene que verlo.
    conOperadoresDeContrasena();

    for ($intento = 1; $intento <= 5; $intento++) {
        ingresarComo('ana@agropartners.com.bo', 'otra-clave');
    }

    $frenado = ingresarComo('ana@agropartners.com.bo', CLAVE_DE_ANA);

    $frenado->assertRedirect(route('admin.formulario'))
        ->assertSessionHas('error', fn (string $error): bool => str_contains($error, 'intentos'));

    expect(SesionDeOperador::actual())->toBeNull();
});

it('el freno no se lleva puesto a la otra persona', function () {
    // Contarlos por correo y no solo por IP: los dos operadores comparten la
    // salida a internet de la oficina, así que un contador por IP dejaría que
    // uno bloquee al otro equivocándose cinco veces.
    conOperadoresDeContrasena();

    for ($intento = 1; $intento <= 5; $intento++) {
        ingresarComo('ana@agropartners.com.bo', 'otra-clave');
    }

    ingresarComo('bruno@agropartners.com.bo', 'Clave-De-Bruno-2')
        ->assertRedirectContains('/admin/callback');
});

it('un POST sin haber pasado por entrar no abre sesion', function () {
    conOperadoresDeContrasena();

    $this->post('/admin/verificar', ['correo' => 'ana@agropartners.com.bo', 'contrasena' => CLAVE_DE_ANA])
        ->assertRedirect(route('admin.entrar'));

    expect(SesionDeOperador::actual())->toBeNull();
});

it('con el autenticador de desarrollo el formulario no existe', function () {
    // Las rutas se registran siempre para no depender de que la configuración
    // sea la misma cuando se cachean; el que no corresponde responde 404.
    $this->get('/admin/formulario')->assertNotFound();
});

it('el error del callback llega hasta el formulario', function () {
    // `/admin/entrar` es un rebote hacia el formulario, asi que el mensaje del
    // callback tiene que sobrevivir dos requests, no uno. Sin reflash, el
    // operador ve el formulario vacio y no se enteraria de por que volvio.
    conOperadoresDeContrasena();
    $this->get('/admin/entrar');

    $this->get('/admin/callback?code=inventado&state=un-estado-que-no-emitimos')
        ->assertRedirect(route('admin.entrar'));

    $this->followingRedirects()->get('/admin/entrar')->assertSee('El ingreso expiró');
});
