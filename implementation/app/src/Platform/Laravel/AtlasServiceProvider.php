<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel;

use Atlas\Composition\Billing\QuoteAcceptedWinOpportunityConsumer;
use Atlas\Composition\Billing\WinOpportunityFromQuoteHandler;
use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Atlas\Composition\Onboarding\Infrastructure\PostgresBootstrapWorkflowRepository;
use Atlas\Modules\Crm\Application\AddContactHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Crm\Application\CreateOpportunityHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Crm\Application\QualifyOpportunityHandler;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
use Atlas\Modules\Billing\Application\AcceptQuoteHandler;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\Billing\Application\CreateFinalInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\IssueInvoiceHandler;
use Atlas\Modules\Billing\Application\RecordPaymentHandler;
use Atlas\Modules\Billing\Application\SendInvoiceHandler;
use Atlas\Modules\Billing\Application\SendQuoteHandler;
use Atlas\Modules\Billing\Application\UpdateQuoteDraftHandler;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPaymentRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresPublicDocumentProofRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresQuoteRepository;
use Atlas\Modules\Billing\Infrastructure\PostgresBillingIdempotencyStore;
use Atlas\Modules\Identity\Application\BootstrapIdentityForWorkspaceHandler;
use Atlas\Modules\Identity\Application\CreateSessionHandler;
use Atlas\Modules\Identity\Application\GetWorkspaceOwnerReadinessHandler;
use Atlas\Modules\Identity\Application\RegisterUserHandler;
use Atlas\Modules\Identity\Application\VerifyUserEmailHandler;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresEmailVerificationRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresRoleRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresSessionRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Identity\Infrastructure\PostgresIdempotencyStore;
use Atlas\Modules\Workspace\Application\ActivateWorkspaceHandler;
use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Modules\Workspace\Infrastructure\Persistence\PostgresWorkspaceRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Atlas\Platform\Messaging\Infrastructure\PostgresInboxStore;
use Atlas\Platform\Messaging\Infrastructure\PostgresOutboxWriter;
use Atlas\Platform\Messaging\InboxStore;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\Spike\SpikeEventCounterConsumer;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Support\ServiceProvider;

final class AtlasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxWriter::class, PostgresOutboxWriter::class);
        $this->app->singleton(InboxStore::class, PostgresInboxStore::class);
        $this->app->singleton(WorkspaceRepository::class, PostgresWorkspaceRepository::class);

        $this->app->singleton(SpikeEventCounterConsumer::class);
        $this->app->singleton(OutboxProcessor::class, function ($app): OutboxProcessor {
            return new OutboxProcessor(
                $app->make(InboxStore::class),
                [
                    $app->make(SpikeEventCounterConsumer::class),
                    $app->make(QuoteAcceptedWinOpportunityConsumer::class),
                ],
            );
        });

        $this->app->singleton(PostgresUserRepository::class);
        $this->app->singleton(PostgresSessionRepository::class);
        $this->app->singleton(PostgresRoleRepository::class);
        $this->app->singleton(PostgresMembershipRepository::class);
        $this->app->singleton(PostgresEmailVerificationRepository::class);
        $this->app->singleton(PostgresIdempotencyStore::class);
        $this->app->singleton(PostgresBootstrapWorkflowRepository::class);

        $this->app->singleton(RegisterUserHandler::class);
        $this->app->singleton(VerifyUserEmailHandler::class);
        $this->app->singleton(CreateSessionHandler::class);
        $this->app->singleton(BootstrapIdentityForWorkspaceHandler::class);
        $this->app->singleton(GetWorkspaceOwnerReadinessHandler::class);
        $this->app->singleton(CreateWorkspaceHandler::class);
        $this->app->singleton(ActivateWorkspaceHandler::class);
        $this->app->singleton(BootstrapFirstWorkspaceHandler::class);

        $this->app->singleton(WorkspaceAuthorizer::class);
        $this->app->singleton(PostgresCrmIdempotencyStore::class);
        $this->app->singleton(PostgresClientRepository::class);
        $this->app->singleton(PostgresContactRepository::class);
        $this->app->singleton(PostgresOpportunityRepository::class);
        $this->app->singleton(CreateClientHandler::class);
        $this->app->singleton(AddContactHandler::class);
        $this->app->singleton(CreateOpportunityHandler::class);
        $this->app->singleton(QualifyOpportunityHandler::class);
        $this->app->singleton(CrmQueryHandler::class);

        $this->app->singleton(PostgresBillingIdempotencyStore::class);
        $this->app->singleton(PostgresQuoteRepository::class);
        $this->app->singleton(PostgresInvoiceRepository::class);
        $this->app->singleton(PostgresPaymentRepository::class);
        $this->app->singleton(PostgresPublicDocumentProofRepository::class);
        $this->app->singleton(CreateQuoteHandler::class);
        $this->app->singleton(UpdateQuoteDraftHandler::class);
        $this->app->singleton(SendQuoteHandler::class);
        $this->app->singleton(AcceptQuoteHandler::class);
        $this->app->singleton(CreateFinalInvoiceFromQuoteHandler::class);
        $this->app->singleton(IssueInvoiceHandler::class);
        $this->app->singleton(SendInvoiceHandler::class);
        $this->app->singleton(RecordPaymentHandler::class);
        $this->app->singleton(BillingQueryHandler::class);
        $this->app->singleton(WinOpportunityFromQuoteHandler::class);
        $this->app->singleton(QuoteAcceptedWinOpportunityConsumer::class);
    }
}
