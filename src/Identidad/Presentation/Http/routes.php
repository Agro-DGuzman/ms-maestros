<?php

declare(strict_types=1);

use Identidad\Presentation\Http\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('otp', [AuthController::class, 'otp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    // Cierra la sesión de quien la pide: sin token no se sabe de quién es.
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth.token');
});
