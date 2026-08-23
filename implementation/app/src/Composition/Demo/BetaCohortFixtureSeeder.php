<?php

declare(strict_types=1);

namespace Atlas\Composition\Demo;

use Atlas\Composition\Onboarding\BootstrapFirstWorkspaceHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\SendQuoteHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Identity\Application\CreateSessionHandler;
use Atlas\Modules\Identity\Application\RegisterUserHandler;
use Atlas\Modules\Identity\Application\VerifyUserEmailHandler;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Modules\Operations\Contracts\BetaCohortSource;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresBetaCohortRegistry;
use Illuminate\Support\Facades\DB;

final class BetaCohortFixtureSeeder
{
    public const PASSWORD = 'BetaAtlas2026!';

    private const VERSION = '1';

    private const PARTICIPANTS = [
        ['code' => 'BETA-001', 'cell' => 'P19', 'stage' => 'E2', 'segment' => 'primary', 'channel' => 'recommendation'],
        ['code' => 'BETA-002', 'cell' => 'P24', 'stage' => 'E3', 'segment' => 'primary', 'channel' => 'organic'],
        ['code' => 'BETA-003', 'cell' => 'P29', 'stage' => 'E4', 'segment' => 'primary', 'channel' => 'community'],
        ['code' => 'BETA-004', 'cell' => 'P19', 'stage' => 'E5', 'segment' => 'secondary', 'channel' => 'outbound'],
        ['code' => 'BETA-005', 'cell' => 'P24', 'stage' => 'E6', 'segment' => 'anti-persona', 'channel' => 'other'],
    ];

    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly RegisterUserHandler $registerUser,
        private readonly VerifyUserEmailHandler $verifyEmail,
        private readonly CreateSessionHandler $createSession,
        private readonly BootstrapFirstWorkspaceHandler $bootstrapWorkspace,
        private readonly PostgresBetaCohortRegistry $registry,
        private readonly CreateClientHandler $createClient,
        private readonly CreateQuoteHandler $createQuote,
        private readonly SendQuoteHandler $sendQuote,
        private readonly BetaCohortSource $cohort,
    ) {}

    /** @return list<array{beta_code: string, email: string, password: string, workspace_id: string, pricing_cell: string, current_stage: string}> */
    public function seed(): array
    {
        foreach (self::PARTICIPANTS as $index => $definition) {
            $email = strtolower($definition['code']).'@atlas.test';
            [$userId, $workspaceId] = $this->ensureAccount(
                email: $email,
                displayName: 'Participant '.$definition['code'],
                workspaceName: 'Espace '.$definition['code'],
                requestPrefix: $this->requestPrefix($definition['code']),
            );
            $this->ensureEnrollment($definition, $userId, $workspaceId, $index);
            $this->ensureStage($definition['code'], $definition['stage'], $userId, $workspaceId);
        }

        $stages = collect($this->cohort->participants(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 7))
            ->keyBy('beta_code');

        return array_map(static function (array $definition) use ($stages): array {
            $participant = $stages->get($definition['code']);

            return [
                'beta_code' => $definition['code'],
                'email' => strtolower($definition['code']).'@atlas.test',
                'password' => self::PASSWORD,
                'workspace_id' => (string) DB::table('operations.beta_participants')->where('beta_code', $definition['code'])->value('workspace_id'),
                'pricing_cell' => $definition['cell'],
                'current_stage' => (string) ($participant['current_stage'] ?? 'E0'),
            ];
        }, self::PARTICIPANTS);
    }

    /** @return array{string, string} */
    private function ensureAccount(string $email, string $displayName, string $workspaceName, string $requestPrefix): array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            $registered = $this->registerUser->handle(
                email: $email,
                displayName: $displayName,
                password: self::PASSWORD,
                requestId: $requestPrefix.':register',
            );
            $this->verifyEmail->handle($registered['user_id'], $registered['verification_token']);
            $userId = $registered['user_id'];
        } else {
            $userId = $user->id()->value;
        }

        $workspaceId = DB::table('identity.memberships as memberships')
            ->join('workspace.workspaces as workspaces', 'workspaces.id', '=', 'memberships.workspace_id')
            ->where('memberships.user_id', $userId)
            ->where('memberships.status', 'Active')
            ->where('workspaces.status', 'Active')
            ->orderBy('memberships.created_at')
            ->value('memberships.workspace_id');
        if ($workspaceId === null) {
            $workspace = $this->bootstrapWorkspace->handle(
                userId: $userId,
                workspaceName: $workspaceName,
                idempotencyKey: $requestPrefix.':bootstrap',
            );
            $workspaceId = $workspace['workspace_id'];
        }

        if (! DB::table('identity.sessions')->where('user_id', $userId)->exists()) {
            $this->createSession->handle($email, self::PASSWORD);
        }

        return [$userId, (string) $workspaceId];
    }

    private function ensureEnrollment(array $definition, string $userId, string $workspaceId, int $index): void
    {
        $participant = DB::table('operations.beta_participants')->where('beta_code', $definition['code'])->first();
        if ($participant !== null) {
            if ($participant->user_id !== $userId || $participant->workspace_id !== $workspaceId) {
                throw new \DomainException($definition['code'].' is already assigned to another fixture.');
            }

            return;
        }

        $this->registry->enroll(
            betaCode: $definition['code'],
            userId: $userId,
            workspaceId: $workspaceId,
            pricingCell: $definition['cell'],
            packagingVersion: 'Atlas-Solo@1',
            segment: $definition['segment'],
            channel: $definition['channel'],
            invitedAt: new \DateTimeImmutable('-'.(12 - $index).' days'),
        );
    }

    private function ensureStage(string $betaCode, string $targetStage, string $userId, string $workspaceId): void
    {
        if ($targetStage === 'E2') {
            return;
        }

        $prefix = $this->requestPrefix($betaCode);
        $client = $this->createClient->handle(
            actorUserId: $userId,
            workspaceId: $workspaceId,
            kind: 'Organization',
            displayName: 'Client test '.$betaCode,
            profile: ['fixture' => $betaCode],
            billingProfile: [
                'billing_name' => 'Client test '.$betaCode,
                'billing_email' => strtolower($betaCode).'-client@atlas.test',
            ],
            requestId: $prefix.':client',
        );
        $quote = $this->createQuote->handle(
            actorUserId: $userId,
            workspaceId: $workspaceId,
            clientId: $client['client_id'],
            opportunityId: null,
            lines: [['description' => 'Prestation beta', 'quantity' => 1, 'unit_price_cents' => 100000]],
            currency: 'EUR',
            requestId: $prefix.':quote',
        );

        if ($targetStage === 'E3') {
            $this->ensureReview($betaCode, 'J2', 'onboarding-data', 15, 'review-data-setup');

            return;
        }

        $sent = $this->sendQuote->handle(
            actorUserId: $userId,
            workspaceId: $workspaceId,
            quoteId: $quote['quote_id'],
            expectedRevision: (int) $quote['version'],
            requestId: $prefix.':send-quote',
        );

        if (in_array($targetStage, ['E5', 'E6'], true)) {
            DB::table('billing.quotes')->where('id', $sent['quote_id'])->update([
                'sent_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]);
            $this->createClient->handle(
                actorUserId: $userId,
                workspaceId: $workspaceId,
                kind: 'Organization',
                displayName: 'Client réutilisation '.$betaCode,
                profile: ['fixture' => $betaCode, 'reuse' => true],
                billingProfile: null,
                requestId: $prefix.':reuse-client',
            );
            $this->ensureReview($betaCode, 'J7', null, 30, 'continue-week-two');
        }

        if ($targetStage === 'E6' && ! DB::table('operations.pricing_observations')
            ->join('operations.beta_participants', 'operations.beta_participants.id', '=', 'operations.pricing_observations.participant_id')
            ->where('operations.beta_participants.beta_code', $betaCode)
            ->exists()) {
            $this->registry->recordDecision(
                betaCode: $betaCode,
                decision: 'TRIAL_COMMITTED',
                primaryReason: 'context',
                preference: 'Monthly',
                evidenceRef: 'fixture:'.$betaCode,
                observedOn: new \DateTimeImmutable('today'),
            );
        }
    }

    private function ensureReview(string $betaCode, string $milestone, ?string $blockage, int $supportMinutes, string $nextAction): void
    {
        $exists = DB::table('operations.beta_reviews')
            ->join('operations.beta_participants', 'operations.beta_participants.id', '=', 'operations.beta_reviews.participant_id')
            ->where('operations.beta_participants.beta_code', $betaCode)
            ->where('operations.beta_reviews.milestone', $milestone)
            ->exists();
        if ($exists) {
            return;
        }

        $this->registry->review(
            betaCode: $betaCode,
            milestone: $milestone,
            blockageCode: $blockage,
            supportMinutes: $supportMinutes,
            nextAction: $nextAction,
            reviewedAt: new \DateTimeImmutable('now'),
        );
    }

    private function requestPrefix(string $betaCode): string
    {
        return 'beta-fixture:v'.self::VERSION.':'.strtolower($betaCode);
    }
}
