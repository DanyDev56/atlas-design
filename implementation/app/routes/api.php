<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BootstrapWorkspaceController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\RegisterUserController;
use App\Http\Controllers\Api\SpikeCreateWorkspaceController;
use App\Http\Controllers\Api\VerifyEmailController;
use Atlas\Platform\Laravel\Http\Middleware\BearerSessionMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(CorrelationIdMiddleware::class)->group(function (): void {
    Route::post('/auth/register', RegisterUserController::class);
    Route::post('/auth/verify-email', VerifyEmailController::class);
    Route::post('/auth/login', LoginController::class);

    Route::post('/spike/workspaces', SpikeCreateWorkspaceController::class);

    Route::middleware(BearerSessionMiddleware::class)->group(function (): void {
        Route::post('/workspaces/first', BootstrapWorkspaceController::class);
    });
});
