<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BootstrapWorkspaceController;
use App\Http\Controllers\Api\Crm\ClientController;
use App\Http\Controllers\Api\Crm\OpportunityController;
use App\Http\Controllers\Api\Crm\PipelineController;
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

        Route::prefix('/workspaces/{workspaceId}')->group(function (): void {
            Route::get('/clients', [ClientController::class, 'index']);
            Route::post('/clients', [ClientController::class, 'store']);
            Route::get('/clients/{clientId}', [ClientController::class, 'show']);
            Route::post('/clients/{clientId}/contacts', [ClientController::class, 'addContact']);
            Route::get('/clients/{clientId}/billing-context', [ClientController::class, 'billingContext']);

            Route::get('/opportunities', [OpportunityController::class, 'index']);
            Route::post('/opportunities', [OpportunityController::class, 'store']);
            Route::get('/opportunities/{opportunityId}', [OpportunityController::class, 'show']);
            Route::post('/opportunities/{opportunityId}/qualify', [OpportunityController::class, 'qualify']);
            Route::get('/opportunities/{opportunityId}/commercial-context', [OpportunityController::class, 'commercialContext']);

            Route::get('/pipeline', PipelineController::class);
        });
    });
});
