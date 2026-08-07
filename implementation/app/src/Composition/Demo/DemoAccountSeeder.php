<?php

declare(strict_types=1);

namespace Atlas\Composition\Demo;

use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Atlas\Modules\Analytics\Application\PublishAnalyticsSnapshotHandler;
use Atlas\Modules\Billing\Application\AcceptQuoteHandler;
use Atlas\Modules\Billing\Application\CreateFinalInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\IssueInvoiceHandler;
use Atlas\Modules\Billing\Application\RecordPaymentHandler;
use Atlas\Modules\Billing\Application\SendQuoteHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Crm\Application\CreateOpportunityHandler;
use Atlas\Modules\Crm\Application\QualifyOpportunityHandler;
use Atlas\Modules\Identity\Application\RegisterUserHandler;
use Atlas\Modules\Identity\Application\VerifyUserEmailHandler;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoAccountSeeder
{
    public const EMAIL = 'demo@atlas.test';

    public const PASSWORD = 'DemoAtlas2026!';

    public const DISPLAY_NAME = 'Présentation Atlas';

    public const WORKSPACE_NAME = 'Studio Atlas Démo';

    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly RegisterUserHandler $registerUser,
        private readonly VerifyUserEmailHandler $verifyEmail,
        private readonly BootstrapFirstWorkspaceHandler $bootstrapWorkspace,
        private readonly CreateClientHandler $createClient,
        private readonly CreateOpportunityHandler $createOpportunity,
        private readonly QualifyOpportunityHandler $qualifyOpportunity,
        private readonly CreateQuoteHandler $createQuote,
        private readonly SendQuoteHandler $sendQuote,
        private readonly AcceptQuoteHandler $acceptQuote,
        private readonly CreateFinalInvoiceFromQuoteHandler $createInvoiceFromQuote,
        private readonly IssueInvoiceHandler $issueInvoice,
        private readonly RecordPaymentHandler $recordPayment,
        private readonly PublishAnalyticsSnapshotHandler $publishSnapshot,
        private readonly OutboxProcessor $outbox,
    ) {}

    public function seed(): DemoSeedResult
    {
        $userCreated = false;
        $user = $this->users->findByEmail(self::EMAIL);

        if ($user === null) {
            $registered = $this->registerUser->handle(
                email: self::EMAIL,
                displayName: self::DISPLAY_NAME,
                password: self::PASSWORD,
                requestId: $this->requestId('register'),
            );
            $this->verifyEmail->handle($registered['user_id'], $registered['verification_token']);
            $userCreated = true;
            $userId = $registered['user_id'];
        } else {
            $userId = $user->id()->value;
        }

        $workspaceId = $this->resolveWorkspaceId($userId);

        if ($workspaceId === null) {
            $bootstrapped = $this->bootstrapWorkspace->handle(
                userId: $userId,
                workspaceName: self::WORKSPACE_NAME,
                idempotencyKey: $this->requestId('bootstrap'),
            );
            $workspaceId = $bootstrapped['workspace_id'];
        }

        $sampleDataSeeded = false;

        if ($this->workspaceHasNoClients($workspaceId)) {
            $this->seedSampleData($userId, $workspaceId);
            $this->drainOutbox();
            $sampleDataSeeded = true;
        }

        return new DemoSeedResult(
            email: self::EMAIL,
            password: self::PASSWORD,
            userId: $userId,
            workspaceId: $workspaceId,
            userCreated: $userCreated,
            sampleDataSeeded: $sampleDataSeeded,
        );
    }

    private function seedSampleData(string $actorUserId, string $workspaceId): void
    {
        $ateliers = $this->createClient->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            kind: 'Organization',
            displayName: 'Les Ateliers du Marais',
            profile: ['email' => 'contact@ateliers-marais.test'],
            billingProfile: null,
            requestId: $this->requestId('client-ateliers'),
        );

        $this->createOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $ateliers['client_id'],
            contactId: null,
            title: 'Audit express',
            estimatedAmountCents: 80000,
            currency: 'EUR',
            requestId: $this->requestId('opp-audit'),
        );

        $refonte = $this->createOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $ateliers['client_id'],
            contactId: null,
            title: 'Refonte identité visuelle',
            estimatedAmountCents: 350000,
            currency: 'EUR',
            requestId: $this->requestId('opp-refonte'),
        );

        $refonteQualified = $this->qualifyOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            opportunityId: $refonte['opportunity_id'],
            expectedRevision: (int) $refonte['version'],
            requestId: $this->requestId('qualify-refonte'),
        );

        $this->createQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $ateliers['client_id'],
            opportunityId: $refonte['opportunity_id'],
            lines: [
                ['description' => 'Direction artistique', 'quantity' => 1, 'unit_price_cents' => 200000],
                ['description' => 'Déclinaisons supports', 'quantity' => 1, 'unit_price_cents' => 150000],
            ],
            currency: 'EUR',
            requestId: $this->requestId('quote-draft'),
        );

        $horizon = $this->createClient->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            kind: 'Organization',
            displayName: 'Horizon Digital',
            profile: ['email' => 'projets@horizon-digital.test'],
            billingProfile: null,
            requestId: $this->requestId('client-horizon'),
        );

        $migration = $this->createOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $horizon['client_id'],
            contactId: null,
            title: 'Migration cloud',
            estimatedAmountCents: 120000,
            currency: 'EUR',
            requestId: $this->requestId('opp-migration'),
        );

        $migrationQualified = $this->qualifyOpportunity->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            opportunityId: $migration['opportunity_id'],
            expectedRevision: (int) $migration['version'],
            requestId: $this->requestId('qualify-migration'),
        );

        $quote = $this->createQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            clientId: $horizon['client_id'],
            opportunityId: $migration['opportunity_id'],
            lines: [
                ['description' => 'Migration infrastructure', 'quantity' => 1, 'unit_price_cents' => 120000],
            ],
            currency: 'EUR',
            requestId: $this->requestId('quote-migration'),
        );

        $sent = $this->sendQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            expectedRevision: (int) $quote['version'],
            requestId: $this->requestId('send-migration'),
        );

        $this->acceptQuote->handle(
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            publicToken: $sent['public_accept_token'],
            expectedRevision: (int) $sent['version'],
            requestId: $this->requestId('accept-migration'),
        );

        $this->drainOutbox();

        $invoice = $this->createInvoiceFromQuote->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            requestId: $this->requestId('invoice-migration'),
        );

        $issued = $this->issueInvoice->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            expectedRevision: (int) $invoice['version'],
            requestId: $this->requestId('issue-migration'),
        );

        $this->recordPayment->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            invoiceId: $invoice['invoice_id'],
            amountCents: 120000,
            reference: 'VIR-DEMO-HORIZON',
            requestId: $this->requestId('payment-migration'),
        );

        unset($refonteQualified, $migrationQualified, $issued);

        $this->drainOutbox(times: 4);

        $this->publishSnapshot->handle(
            actorUserId: $actorUserId,
            workspaceId: $workspaceId,
            requestId: $this->requestId('analytics-snapshot'),
        );

        $this->drainOutbox(times: 4);
    }

    private function drainOutbox(int $times = 3): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->outbox->processPending();
        }
    }

    private function workspaceHasNoClients(string $workspaceId): bool
    {
        return DB::table('crm.clients')->where('workspace_id', $workspaceId)->count() === 0;
    }

    private function resolveWorkspaceId(string $userId): ?string
    {
        $workspaceId = DB::table('identity.memberships')
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->value('workspace_id');

        return is_string($workspaceId) ? $workspaceId : null;
    }

    private function requestId(string $suffix): string
    {
        return 'demo-seed:'.$suffix.':'.Str::uuid();
    }
}
