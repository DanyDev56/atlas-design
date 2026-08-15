<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel;

use Atlas\Composition\Advisor\OutboxAdvisorEvaluateConsumer;
use Atlas\Composition\Analytics\OutboxAnalyticsIngestConsumer;
use Atlas\Composition\Analytics\SourceFactSummaryBuilder;
use Atlas\Composition\Billing\QuoteAcceptedWinOpportunityConsumer;
use Atlas\Composition\Billing\WinOpportunityFromQuoteHandler;
use Atlas\Composition\BusinessHealth\OutboxBusinessHealthEvaluateConsumer;
use Atlas\Composition\Dashboard\DashboardQueryHandler;
use Atlas\Composition\Notifications\OutboxNotificationsProcessConsumer;
use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Atlas\Composition\Onboarding\Infrastructure\PostgresBootstrapWorkflowRepository;
use Atlas\Modules\Advisor\Application\AdvisorQueryHandler;
use Atlas\Modules\Advisor\Application\EvaluateRecommendationsHandler;
use Atlas\Modules\Advisor\Application\RecommendationDecisionHandler;
use Atlas\Modules\Advisor\Application\RecommendationPolicyEvaluator;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresAdvisorOverviewRepository;
use Atlas\Modules\Advisor\Infrastructure\Persistence\PostgresRecommendationRepository;
use Atlas\Modules\Advisor\Infrastructure\PostgresAdvisorIdempotencyStore;
use Atlas\Modules\Analytics\Application\AnalyticsQueryHandler;
use Atlas\Modules\Analytics\Application\IngestSourceFactHandler;
use Atlas\Modules\Analytics\Application\MetricCalculator;
use Atlas\Modules\Analytics\Application\PublishAnalyticsSnapshotHandler;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsFactRepository;
use Atlas\Modules\Analytics\Infrastructure\Persistence\PostgresAnalyticsSnapshotRepository;
use Atlas\Modules\Analytics\Infrastructure\PostgresAnalyticsIdempotencyStore;
use Atlas\Modules\Billing\Application\AcceptQuoteHandler;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\Billing\Application\CreateFinalInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\GetInvoiceAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetPaymentAnalyticsFactHandler;
use Atlas\Modules\Billing\Application\GetQuoteAnalyticsFactHandler;
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
use Atlas\Modules\BusinessHealth\Application\BusinessHealthQueryHandler;
use Atlas\Modules\BusinessHealth\Application\EvaluateBusinessHealthHandler;
use Atlas\Modules\BusinessHealth\Application\HealthPolicyEvaluator;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresBusinessHealthAssessmentRepository;
use Atlas\Modules\BusinessHealth\Infrastructure\Persistence\PostgresCurrentBusinessHealthRepository;
use Atlas\Modules\BusinessHealth\Infrastructure\PostgresBusinessHealthIdempotencyStore;
use Atlas\Modules\Crm\Application\AddContactHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Crm\Application\CreateOpportunityHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Crm\Application\GetOpportunityAnalyticsFactHandler;
use Atlas\Modules\Crm\Application\QualifyOpportunityHandler;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Modules\Crm\Infrastructure\PostgresCrmIdempotencyStore;
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
use Atlas\Modules\Notifications\Application\ChangeNotificationPreferencesHandler;
use Atlas\Modules\Notifications\Application\MarkNotificationReadHandler;
use Atlas\Modules\Notifications\Application\NotificationPlanEvaluator;
use Atlas\Modules\Notifications\Application\NotificationQueryHandler;
use Atlas\Modules\Notifications\Application\ProcessAdvisorNotificationSignalHandler;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationPreferenceRepository;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationTopicCursorRepository;
use Atlas\Modules\Notifications\Infrastructure\PostgresNotificationsIdempotencyStore;
use Atlas\Modules\Workspace\Application\ActivateWorkspaceHandler;
use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Application\WorkspaceSummaryQueryHandler;
use Atlas\Modules\Workspace\Domain\WorkspaceRepository;
use Atlas\Modules\Workspace\Infrastructure\Persistence\PostgresWorkspaceRepository;
use Atlas\Platform\Messaging\InboxStore;
use Atlas\Platform\Messaging\Infrastructure\OutboxBacklogMonitor;
use Atlas\Platform\Messaging\Infrastructure\OutboxDeadLetterManager;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Atlas\Platform\Messaging\Infrastructure\PostgresInboxStore;
use Atlas\Platform\Messaging\Infrastructure\PostgresOutboxWriter;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\Spike\SpikeEventCounterConsumer;
use Atlas\Platform\Observability\Telemetry;
use Atlas\Platform\Retention\Infrastructure\RetentionPurger;
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
                    $app->make(OutboxAnalyticsIngestConsumer::class),
                    $app->make(OutboxBusinessHealthEvaluateConsumer::class),
                    $app->make(OutboxAdvisorEvaluateConsumer::class),
                    $app->make(OutboxNotificationsProcessConsumer::class),
                ],
                $app->make(OutboxBacklogMonitor::class),
                maxAttempts: (int) config('platform.outbox.max_attempts', 5),
                retryBaseSeconds: (int) config('platform.outbox.retry_base_seconds', 5),
                retryMaxSeconds: (int) config('platform.outbox.retry_max_seconds', 300),
            );
        });

        $this->app->singleton(OutboxBacklogMonitor::class);
        $this->app->singleton(OutboxDeadLetterManager::class);
        $this->app->singleton(RetentionPurger::class);

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
        $this->app->singleton(WorkspaceSummaryQueryHandler::class);
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
        $this->app->singleton(GetOpportunityAnalyticsFactHandler::class);
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
        $this->app->singleton(GetQuoteAnalyticsFactHandler::class);
        $this->app->singleton(GetInvoiceAnalyticsFactHandler::class);
        $this->app->singleton(GetPaymentAnalyticsFactHandler::class);
        $this->app->singleton(WinOpportunityFromQuoteHandler::class);
        $this->app->singleton(QuoteAcceptedWinOpportunityConsumer::class);

        $this->app->singleton(PostgresAnalyticsIdempotencyStore::class);
        $this->app->singleton(PostgresAnalyticsFactRepository::class);
        $this->app->singleton(PostgresAnalyticsSnapshotRepository::class);
        $this->app->singleton(MetricCalculator::class);
        $this->app->singleton(IngestSourceFactHandler::class);
        $this->app->singleton(PublishAnalyticsSnapshotHandler::class);
        $this->app->singleton(AnalyticsQueryHandler::class);
        $this->app->singleton(OutboxAnalyticsIngestConsumer::class);

        $this->app->singleton(SourceFactSummaryBuilder::class);
        $this->app->singleton(PostgresBusinessHealthIdempotencyStore::class);
        $this->app->singleton(PostgresBusinessHealthAssessmentRepository::class);
        $this->app->singleton(PostgresCurrentBusinessHealthRepository::class);
        $this->app->singleton(HealthPolicyEvaluator::class);
        $this->app->singleton(EvaluateBusinessHealthHandler::class);
        $this->app->singleton(BusinessHealthQueryHandler::class);
        $this->app->singleton(OutboxBusinessHealthEvaluateConsumer::class);

        $this->app->singleton(PostgresAdvisorIdempotencyStore::class);
        $this->app->singleton(PostgresRecommendationRepository::class);
        $this->app->singleton(PostgresAdvisorOverviewRepository::class);
        $this->app->singleton(RecommendationPolicyEvaluator::class);
        $this->app->singleton(EvaluateRecommendationsHandler::class);
        $this->app->singleton(RecommendationDecisionHandler::class);
        $this->app->singleton(AdvisorQueryHandler::class);
        $this->app->singleton(OutboxAdvisorEvaluateConsumer::class);

        $this->app->singleton(PostgresNotificationsIdempotencyStore::class);
        $this->app->singleton(PostgresNotificationRepository::class);
        $this->app->singleton(PostgresNotificationPreferenceRepository::class);
        $this->app->singleton(PostgresNotificationTopicCursorRepository::class);
        $this->app->singleton(NotificationPlanEvaluator::class);
        $this->app->singleton(ProcessAdvisorNotificationSignalHandler::class);
        $this->app->singleton(NotificationQueryHandler::class);
        $this->app->singleton(MarkNotificationReadHandler::class);
        $this->app->singleton(ChangeNotificationPreferencesHandler::class);
        $this->app->singleton(OutboxNotificationsProcessConsumer::class);
        $this->app->singleton(DashboardQueryHandler::class);
    }

    public function boot(): void
    {
        Telemetry::configure(
            tracesExporter: (string) config('otel.traces_exporter', 'none'),
            serviceName: (string) config('otel.service_name', 'atlas-app'),
            otlpEndpoint: (string) config('otel.exporter_otlp_endpoint', 'http://otel-collector:4318'),
        );
        Telemetry::bootstrap();
    }
}
