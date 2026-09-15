<?php

declare(strict_types=1);

use BackOffice\Presentation\Http\AccesosController;
use BackOffice\Presentation\Http\SesionController;
use Illuminate\Support\Facades\Route;

/*
 * Siete rutas, ninguna bajo /v1/. `backoffice.ip` envuelve tambien a entrar y
 * callback: la restriccion por IP es anterior al login, no posterior.
 */
Route::prefix('admin')->name('admin.')->middleware(['web', 'backoffice.ip'])->group(function (): void {
    Route::get('/entrar', [SesionController::class, 'entrar'])->name('entrar');
    Route::get('/callback', [SesionController::class, 'callback'])->name('callback');

    Route::middleware('backoffice.sesion')->group(function (): void {
        Route::post('/salir', [SesionController::class, 'salir'])->name('salir');

        Route::get('/contactos', [AccesosController::class, 'index'])->name('contactos');
        Route::post('/contactos/{id}/habilitar', [AccesosController::class, 'habilitar'])->name('habilitar');
        Route::post('/contactos/{id}/deshabilitar', [AccesosController::class, 'deshabilitar'])->name('deshabilitar');
        Route::get('/contactos/{id}/historial', [AccesosController::class, 'historial'])->name('historial');
    });
});
