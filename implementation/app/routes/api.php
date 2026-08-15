<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Advisor\AdvisorController;
use App\Http\Controllers\Api\Analytics\AnalyticsController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\PublicQuoteAcceptController;
use App\Http\Controllers\Api\Billing\PublicQuoteController;
use App\Http\Controllers\Api\Billing\QuoteController;
use App\Http\Controllers\Api\BootstrapWorkspaceController;
use App\Http\Controllers\Api\BusinessHealth\BusinessHealthController;
use App\Http\Controllers\Api\Crm\ClientController;
use App\Http\Controllers\Api\Crm\OpportunityController;
use App\Http\Controllers\Api\Crm\PipelineController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dev\ProcessOutboxController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\RegisterUserController;
use App\Http\Controllers\Api\RemoveMembershipController;
use App\Http\Controllers\Api\RevokeSessionController;
use App\Http\Controllers\Api\SessionContextController;
use App\Http\Controllers\Api\SpikeCreateWorkspaceController;
use App\Http\Controllers\Api\VerifyEmailController;
use App\Http\Controllers\Api\Workspace\WorkspaceController;
use Atlas\Platform\Laravel\Http\Middleware\BearerSessionMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\CorrelationIdMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\DevelopmentOnlyMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\HttpTracingMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([CorrelationIdMiddleware::class, HttpTracingMiddleware::class])->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/auth/register', RegisterUserController::class);
        Route::post('/auth/verify-email', VerifyEmailController::class);
        Route::post('/auth/login', LoginController::class);
    });

    Route::middleware('throttle:public')->group(function (): void {
        Route::get('/public/workspaces/{workspaceId}/quotes/{quoteId}', PublicQuoteController::class);
        Route::post('/public/workspaces/{workspaceId}/quotes/{quoteId}/accept', PublicQuoteAcceptController::class);
    });

    Route::middleware(DevelopmentOnlyMiddleware::class)->group(function (): void {
        Route::post('/dev/outbox/process', ProcessOutboxController::class);
        Route::post('/spike/workspaces', SpikeCreateWorkspaceController::class);
    });

    Route::middleware(BearerSessionMiddleware::class)->group(function (): void {
        Route::post('/auth/session/revoke', RevokeSessionController::class);
        Route::get('/auth/session/context', SessionContextController::class);

        Route::post('/workspaces/first', BootstrapWorkspaceController::class);

        Route::prefix('/workspaces/{workspaceId}')->group(function (): void {
            Route::get('/summary', [WorkspaceController::class, 'summary']);

            Route::post('/memberships/{membershipId}/remove', RemoveMembershipController::class);

            Route::get('/clients', [ClientController::class, 'index']);
            Route::post('/clients', [ClientController::class, 'store']);
            Route::get('/clients/{clientId}', [ClientController::class, 'show']);
            Route::get('/clients/{clientId}/contacts', [ClientController::class, 'contacts']);
            Route::post('/clients/{clientId}/contacts', [ClientController::class, 'addContact']);
            Route::put('/clients/{clientId}/primary-contact', [ClientController::class, 'changePrimaryContact']);
            Route::get('/clients/{clientId}/billing-context', [ClientController::class, 'billingContext']);

            Route::get('/opportunities', [OpportunityController::class, 'index']);
            Route::post('/opportunities', [OpportunityController::class, 'store']);
            Route::get('/opportunities/{opportunityId}', [OpportunityController::class, 'show']);
            Route::post('/opportunities/{opportunityId}/qualify', [OpportunityController::class, 'qualify']);
            Route::get('/opportunities/{opportunityId}/commercial-context', [OpportunityController::class, 'commercialContext']);

            Route::get('/pipeline', PipelineController::class);

            Route::get('/quotes', [QuoteController::class, 'index']);
            Route::post('/quotes', [QuoteController::class, 'store']);
            Route::get('/quotes/{quoteId}', [QuoteController::class, 'show']);
            Route::patch('/quotes/{quoteId}', [QuoteController::class, 'update']);
            Route::post('/quotes/{quoteId}/send', [QuoteController::class, 'send']);
            Route::post('/quotes/{quoteId}/invoices', [QuoteController::class, 'createInvoice']);

            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show']);
            Route::post('/invoices/{invoiceId}/issue', [InvoiceController::class, 'issue']);
            Route::post('/invoices/{invoiceId}/send', [InvoiceController::class, 'send']);
            Route::post('/invoices/{invoiceId}/payments', [InvoiceController::class, 'recordPayment']);

            Route::get('/analytics/snapshot/latest', [AnalyticsController::class, 'latestSnapshot']);
            Route::post('/analytics/snapshots/publish', [AnalyticsController::class, 'publishSnapshot']);
            Route::get('/analytics/metrics/{metricKey}', [AnalyticsController::class, 'metric']);

            Route::get('/business-health/current', [BusinessHealthController::class, 'current']);
            Route::get('/business-health/assessments/{assessmentId}', [BusinessHealthController::class, 'show']);

            Route::get('/advisor/overview', [AdvisorController::class, 'overview']);
            Route::post('/advisor/recommendations/{recommendationId}/complete', [AdvisorController::class, 'complete']);
            Route::post('/advisor/recommendations/{recommendationId}/dismiss', [AdvisorController::class, 'dismiss']);

            Route::get('/notifications', [NotificationController::class, 'index']);
            Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::get('/notifications/preferences', [NotificationController::class, 'getPreferences']);
            Route::put('/notifications/preferences', [NotificationController::class, 'changePreferences']);
            Route::get('/notifications/{notificationId}', [NotificationController::class, 'show']);
            Route::post('/notifications/{notificationId}/mark-read', [NotificationController::class, 'markRead']);

            Route::get('/dashboard', [DashboardController::class, 'show']);
        });
    });
});
