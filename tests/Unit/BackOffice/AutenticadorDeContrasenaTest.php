<?php

declare(strict_types=1);

use BackOffice\Application\Contracts\IngresoRechazado;
use BackOffice\Infrastructure\Contrasena\AutenticadorDeContrasena;
use BackOffice\Infrastructure\Contrasena\OperadorConContrasena;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;

/** Costo bajo a proposito: la bateria no mide bcrypt, mide la decision. */
function hashDePrueba(string $contrasena): string
{
    return password_hash($contrasena, PASSWORD_BCRYPT, ['cost' => 4]);
}

function autenticadorDeContrasena(): AutenticadorDeContrasena
{
    return new AutenticadorDeContrasena(
        operadores: [
            new OperadorConContrasena(
                correo: 'ana@agropartners.com.bo',
                hash: hashDePrueba('Clave-De-Ana-1'),
                nombre: 'Ana Suárez',
            ),
            new OperadorConContrasena(
                correo: 'bruno@agropartners.com.bo',
                hash: hashDePrueba('Clave-De-Bruno-2'),
                nombre: 'Bruno Ortiz',
            ),
        ],
        cache: new Repository(new ArrayStore),
        urlDelFormulario: 'http://localhost/admin/formulario',
    );
}

it('una contrasena correcta emite un codigo que resuelve al operador', function () {
    $autenticador = autenticadorDeContrasena();

    $codigo = $autenticador->emitirCodigoPara('ana@agropartners.com.bo', 'Clave-De-Ana-1');
    $operador = $autenticador->resolver($codigo, 'verificador-ignorado');

    expect($operador->nombre)->toBe('Ana Suárez')
        ->and($operador->correo)->toBe('ana@agropartners.com.bo');
});

it('rechaza una contrasena equivocada', function () {
    expect(fn () => autenticadorDeContrasena()->emitirCodigoPara('ana@agropartners.com.bo', 'otra-clave'))
        ->toThrow(IngresoRechazado::class);
});

it('un correo que no es de nadie se rechaza igual que una contrasena mala', function () {
    // Mismo mensaje para los dos casos, como el desafío de ingreso: decir
    // «ese correo no existe» le confirma a quien prueba cuáles sí existen.
    $sinCorreo = null;
    $sinContrasena = null;

    try {
        autenticadorDeContrasena()->emitirCodigoPara('nadie@agropartners.com.bo', 'Clave-De-Ana-1');
    } catch (IngresoRechazado $e) {
        $sinCorreo = $e;
    }

    try {
        autenticadorDeContrasena()->emitirCodigoPara('ana@agropartners.com.bo', 'otra-clave');
    } catch (IngresoRechazado $e) {
        $sinContrasena = $e;
    }

    expect($sinCorreo)->not->toBeNull()
        ->and($sinContrasena)->not->toBeNull()
        ->and($sinCorreo->codigo)->toBe($sinContrasena->codigo)
        ->and($sinCorreo->getMessage())->toBe($sinContrasena->getMessage());
});

it('resuelve al operador del codigo y no a otro', function () {
    $autenticador = autenticadorDeContrasena();

    $codigo = $autenticador->emitirCodigoPara('bruno@agropartners.com.bo', 'Clave-De-Bruno-2');

    expect($autenticador->resolver($codigo, 'verificador-ignorado')->nombre)->toBe('Bruno Ortiz');
});

it('el codigo sirve una sola vez', function () {
    // Si el código quedara reusable, el historial del navegador o un Referer
    // filtrado alcanzarían para volver a entrar sin la contraseña.
    $autenticador = autenticadorDeContrasena();
    $codigo = $autenticador->emitirCodigoPara('ana@agropartners.com.bo', 'Clave-De-Ana-1');

    $autenticador->resolver($codigo, 'verificador-ignorado');

    expect(fn () => $autenticador->resolver($codigo, 'verificador-ignorado'))
        ->toThrow(IngresoRechazado::class);
});

it('un codigo que nunca emitimos no resuelve a nadie', function () {
    expect(fn () => autenticadorDeContrasena()->resolver('inventado', 'verificador-ignorado'))
        ->toThrow(IngresoRechazado::class);
});

it('lee la lista de operadores de una sola variable de entorno', function () {
    $lista = OperadorConContrasena::listaDesde(
        'ana@agropartners.com.bo|$2y$04$hashdeana|Ana Suárez;bruno@agropartners.com.bo|$2y$04$hashdebruno|Bruno Ortiz',
    );

    expect($lista)->toHaveCount(2)
        ->and($lista[0]->correo)->toBe('ana@agropartners.com.bo')
        ->and($lista[0]->hash)->toBe('$2y$04$hashdeana')
        ->and($lista[0]->nombre)->toBe('Ana Suárez')
        ->and($lista[1]->nombre)->toBe('Bruno Ortiz');
});

it('sin operadores configurados devuelve una lista vacia', function () {
    expect(OperadorConContrasena::listaDesde(''))->toBe([])
        ->and(OperadorConContrasena::listaDesde('   '))->toBe([]);
});

it('una entrada mal formada falla al leerla y no se saltea', function () {
    // Saltearla en silencio dejaría a una persona sin poder entrar sin que
    // nadie sepa por qué. Que reviente al arrancar es más barato.
    expect(fn () => OperadorConContrasena::listaDesde('ana@agropartners.com.bo|solo-dos-campos'))
        ->toThrow(RuntimeException::class, 'BACKOFFICE_OPERADOR_MAL_FORMADO');
});

it('se niega a construirse sin ningun operador', function () {
    // Sin operadores nadie puede entrar. Es seguro, pero es un error de
    // configuración, y callarlo se descubre recién cuando alguien no puede
    // trabajar.
    expect(fn () => new AutenticadorDeContrasena(
        operadores: [],
        cache: new Repository(new ArrayStore),
        urlDelFormulario: 'http://localhost/admin/formulario',
    ))->toThrow(RuntimeException::class, 'BACKOFFICE_SIN_OPERADORES');
});

it('la url de ingreso es el formulario propio', function () {
    expect(autenticadorDeContrasena()->urlDeIngreso('estado-123', 'desafio-abc'))
        ->toBe('http://localhost/admin/formulario');
});

it('lo que genera para la configuracion es lo mismo que sabe leer', function () {
    // Las dos direcciones del formato en un solo lugar: si una cambiara sola,
    // el registro generado dejaría de servir y nadie se enteraría hasta que un
    // operador no pueda entrar.
    $registro = OperadorConContrasena::registroPara(
        correo: 'ana@agropartners.com.bo',
        contrasena: 'Clave-De-Ana-1',
        nombre: 'Ana Suárez',
    );

    $leidos = OperadorConContrasena::listaDesde($registro);

    expect($leidos)->toHaveCount(1)
        ->and($leidos[0]->correo)->toBe('ana@agropartners.com.bo')
        ->and($leidos[0]->nombre)->toBe('Ana Suárez')
        ->and(password_verify('Clave-De-Ana-1', $leidos[0]->hash))->toBeTrue();
});
