<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maestros\Presentation\Http\ConfiguracionDeLaAppController;
use Maestros\Presentation\Http\MiCuentaController;
use Maestros\Presentation\Http\SociosController;

Route::prefix('v1')->group(function (): void {
    // Pública: la App la consulta al arrancar, antes del login.
    Route::get('version', [ConfiguracionDeLaAppController::class, 'version']);

    Route::middleware('auth.token')->group(function (): void {
        Route::get('mi-cuenta', MiCuentaController::class);
        Route::get('socios', SociosController::class);
        Route::get('bancos', [ConfiguracionDeLaAppController::class, 'bancos']);
        Route::get('contactos/atencion-al-cliente', [ConfiguracionDeLaAppController::class, 'atencionAlCliente']);
    });
});
