<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AcceptWorkspaceInvitationController;
use App\Http\Controllers\Api\AccountRecoveryController;
use App\Http\Controllers\Api\Advisor\AdvisorController;
use App\Http\Controllers\Api\Analytics\AnalyticsController;
use App\Http\Controllers\Api\Billing\BillingHistoryImportController;
use App\Http\Controllers\Api\Billing\CreditNoteController;
use App\Http\Controllers\Api\Billing\DocumentArtifactController;
use App\Http\Controllers\Api\Billing\InvoiceController;
use App\Http\Controllers\Api\Billing\PublicQuoteAcceptController;
use App\Http\Controllers\Api\Billing\PublicQuoteController;
use App\Http\Controllers\Api\Billing\QuoteController;
use App\Http\Controllers\Api\BootstrapWorkspaceController;
use App\Http\Controllers\Api\BusinessHealth\BusinessHealthController;
use App\Http\Controllers\Api\Crm\ActivityController;
use App\Http\Controllers\Api\Crm\ClientController;
use App\Http\Controllers\Api\Crm\ClientHistoryImportController;
use App\Http\Controllers\Api\Crm\OpportunityController;
use App\Http\Controllers\Api\Crm\PipelineController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Dev\ProcessOutboxController;
use App\Http\Controllers\Api\ElevateSessionController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\RegisterUserController;
use App\Http\Controllers\Api\RemoveMembershipController;
use App\Http\Controllers\Api\RevokeSessionController;
use App\Http\Controllers\Api\SessionContextController;
use App\Http\Controllers\Api\SpikeCreateWorkspaceController;
use App\Http\Controllers\Api\Subscriptions\RecurringBillingWebhookController;
use App\Http\Controllers\Api\Subscriptions\SubscriptionController;
use App\Http\Controllers\Api\VerifyEmailController;
use App\Http\Controllers\Api\Workspace\WorkspaceController;
use Atlas\Platform\Laravel\Http\Middleware\BearerSessionMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\CorrelationIdMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\DevelopmentOnlyMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\HttpTracingMiddleware;
use Atlas\Platform\Laravel\Http\Middleware\RequireWorkspaceEntitlementMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([CorrelationIdMiddleware::class, HttpTracingMiddleware::class])->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('/auth/register', RegisterUserController::class);
        Route::post('/auth/verify-email', VerifyEmailController::class);
        Route::post('/auth/login', LoginController::class);
        Route::post('/auth/recovery', [AccountRecoveryController::class, 'request']);
        Route::post('/auth/recovery/complete', [AccountRecoveryController::class, 'complete']);
    });

    Route::middleware('throttle:public')->group(function (): void {
        Route::get('/public/workspaces/{workspaceId}/quotes/{quoteId}', PublicQuoteController::class);
        Route::post('/public/workspaces/{workspaceId}/quotes/{quoteId}/accept', PublicQuoteAcceptController::class);
        Route::post('/subscriptions/webhooks/{provider}', RecurringBillingWebhookController::class);
    });

    Route::middleware(DevelopmentOnlyMiddleware::class)->group(function (): void {
        Route::post('/dev/outbox/process', ProcessOutboxController::class);
        Route::post('/spike/workspaces', SpikeCreateWorkspaceController::class);
    });

    Route::middleware(BearerSessionMiddleware::class)->group(function (): void {
        Route::post('/auth/session/revoke', RevokeSessionController::class);
        Route::middleware('throttle:auth')->post('/auth/session/elevate', ElevateSessionController::class);
        Route::get('/auth/session/context', SessionContextController::class);
        Route::post('/invitations/{invitationId}/accept', AcceptWorkspaceInvitationController::class);

        Route::post('/workspaces/first', BootstrapWorkspaceController::class);

        Route::prefix('/workspaces/{workspaceId}')->group(function (): void {
            Route::get('/summary', [WorkspaceController::class, 'summary']);
            Route::get('/profile', [WorkspaceController::class, 'profile']);
            Route::patch('/profile', [WorkspaceController::class, 'updateProfile'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/billing-identity', [WorkspaceController::class, 'billingIdentity']);
            Route::patch('/billing-identity', [WorkspaceController::class, 'updateBillingIdentity'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/preferences', [WorkspaceController::class, 'preferences']);
            Route::patch('/preferences', [WorkspaceController::class, 'updatePreferences'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/members', [WorkspaceController::class, 'members']);
            Route::get('/invitations', [WorkspaceController::class, 'invitations']);
            Route::post('/invitations', [WorkspaceController::class, 'createInvitation'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':members.invite');
            Route::post('/invitations/{invitationId}/revoke', [WorkspaceController::class, 'revokeInvitation'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':members.invite');

            Route::post('/memberships/{membershipId}/remove', RemoveMembershipController::class)
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':members.invite');

            Route::get('/subscription', [SubscriptionController::class, 'show']);
            Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
            Route::post('/subscription/portal', [SubscriptionController::class, 'portal']);

            Route::get('/clients', [ClientController::class, 'index']);
            Route::post('/clients', [ClientController::class, 'store'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/client-history-imports/preview', [ClientHistoryImportController::class, 'preview']);
            Route::post('/client-history-imports/confirm', [ClientHistoryImportController::class, 'confirm'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/client-history-imports/{importRunId}', [ClientHistoryImportController::class, 'show']);
            Route::get('/clients/{clientId}', [ClientController::class, 'show']);
            Route::patch('/clients/{clientId}/profile', [ClientController::class, 'updateProfile'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::put('/clients/{clientId}/billing-profile', [ClientController::class, 'updateBillingProfile'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/clients/{clientId}/archive', [ClientController::class, 'archive'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/clients/{clientId}/reactivate', [ClientController::class, 'reactivate'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/clients/{clientId}/contacts', [ClientController::class, 'contacts']);
            Route::post('/clients/{clientId}/contacts', [ClientController::class, 'addContact'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::patch('/clients/{clientId}/contacts/{contactId}', [ClientController::class, 'updateContact'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/clients/{clientId}/contacts/{contactId}/archive', [ClientController::class, 'archiveContact'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/clients/{clientId}/contacts/{contactId}/reactivate', [ClientController::class, 'reactivateContact'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::put('/clients/{clientId}/primary-contact', [ClientController::class, 'changePrimaryContact'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/clients/{clientId}/billing-context', [ClientController::class, 'billingContext']);
            Route::get('/clients/{clientId}/activities', [ActivityController::class, 'index']);
            Route::get('/clients/{clientId}/activities/audit', [ActivityController::class, 'audit']);
            Route::post('/clients/{clientId}/activities', [ActivityController::class, 'store'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::patch('/activities/{activityId}', [ActivityController::class, 'update'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/activities/{activityId}/remove', [ActivityController::class, 'remove'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');

            Route::get('/opportunities', [OpportunityController::class, 'index']);
            Route::post('/opportunities', [OpportunityController::class, 'store'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/opportunities/{opportunityId}', [OpportunityController::class, 'show']);
            Route::patch('/opportunities/{opportunityId}', [OpportunityController::class, 'update'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/opportunities/{opportunityId}/qualify', [OpportunityController::class, 'qualify'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/opportunities/{opportunityId}/lose', [OpportunityController::class, 'lose'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/opportunities/{opportunityId}/win', [OpportunityController::class, 'win'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/opportunities/{opportunityId}/commercial-context', [OpportunityController::class, 'commercialContext']);

            Route::get('/pipeline', PipelineController::class);

            Route::get('/quotes', [QuoteController::class, 'index']);
            Route::post('/quotes', [QuoteController::class, 'store'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/billing-history-imports/preview', [BillingHistoryImportController::class, 'preview']);
            Route::post('/billing-history-imports/confirm', [BillingHistoryImportController::class, 'confirm'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/billing-history-imports/{importRunId}', [BillingHistoryImportController::class, 'show']);
            Route::get('/quotes/{quoteId}', [QuoteController::class, 'show']);
            Route::patch('/quotes/{quoteId}', [QuoteController::class, 'update'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/quotes/{quoteId}/send', [QuoteController::class, 'send'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':documents.send');
            Route::post('/quotes/{quoteId}/invoices', [QuoteController::class, 'createInvoice'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/quotes/{quoteId}/deposit-invoices', [QuoteController::class, 'createDepositInvoice'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');

            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/{invoiceId}', [InvoiceController::class, 'show']);
            Route::post('/invoices/{invoiceId}/issue', [InvoiceController::class, 'issue'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/invoices/{invoiceId}/send', [InvoiceController::class, 'send'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':documents.send');
            Route::post('/invoices/{invoiceId}/remind', [InvoiceController::class, 'remind'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':documents.send');
            Route::post('/invoices/{invoiceId}/payments', [InvoiceController::class, 'recordPayment'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/invoices/{invoiceId}/credit-notes', [CreditNoteController::class, 'index']);
            Route::post('/invoices/{invoiceId}/credit-notes', [CreditNoteController::class, 'store'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/credit-notes/{creditNoteId}', [CreditNoteController::class, 'show']);
            Route::patch('/credit-notes/{creditNoteId}', [CreditNoteController::class, 'update'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/credit-notes/{creditNoteId}/discard', [CreditNoteController::class, 'discard'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/credit-notes/{creditNoteId}/issue', [CreditNoteController::class, 'issue'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/credit-notes/{creditNoteId}/apply', [CreditNoteController::class, 'apply'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::get('/documents/{documentType}/{documentId}/artifact', [DocumentArtifactController::class, 'show'])
                ->where('documentType', 'quote|invoice|credit_note');

            Route::get('/analytics/snapshot/latest', [AnalyticsController::class, 'latestSnapshot']);
            Route::get('/analytics/overview', [AnalyticsController::class, 'overview']);
            Route::post('/analytics/snapshots/publish', [AnalyticsController::class, 'publishSnapshot'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':analytics.evaluate');
            Route::get('/analytics/metrics/{metricKey}', [AnalyticsController::class, 'metric']);

            Route::get('/business-health/current', [BusinessHealthController::class, 'current']);
            Route::get('/business-health/assessments/{assessmentId}', [BusinessHealthController::class, 'show']);

            Route::get('/advisor/overview', [AdvisorController::class, 'overview']);
            Route::post('/advisor/recommendations/{recommendationId}/complete', [AdvisorController::class, 'complete'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');
            Route::post('/advisor/recommendations/{recommendationId}/dismiss', [AdvisorController::class, 'dismiss'])
                ->middleware(RequireWorkspaceEntitlementMiddleware::class.':workspace.mutate');

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
