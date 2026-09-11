<?php

declare(strict_types=1);

use Identidad\Presentation\Http\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('otp', [AuthController::class, 'otp']);
});
