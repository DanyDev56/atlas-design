<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Domain\Entitlement;
use Atlas\Modules\Subscriptions\Domain\EntitlementRepository;
use Atlas\Modules\Subscriptions\Domain\PlanCatalogRepository;
use Atlas\Modules\Subscriptions\Domain\Trial;
use Atlas\Modules\Subscriptions\Domain\TrialId;
use Atlas\Modules\Subscriptions\Domain\TrialRepository;
use Atlas\Modules\Subscriptions\Domain\TrialStarted;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class StartTrialForWorkspaceHandler
{
    private const RESTRICTED_CAPABILITIES = [
        'workspace.read',
        'data.export',
        'subscription.manage',
    ];

    public function __construct(
        private readonly PlanCatalogRepository $catalog,
        private readonly TrialRepository $trials,
        private readonly EntitlementRepository $entitlements,
        private readonly OutboxWriter $outbox,
    ) {}

    public function handle(
        string $workspaceId,
        \DateTimeImmutable $activatedAt,
        ?string $correlationId = null,
        ?string $causationId = null,
    ): Trial {
        return DB::transaction(function () use ($workspaceId, $activatedAt, $correlationId, $causationId): Trial {
            $existing = $this->trials->findByWorkspaceId($workspaceId);
            if ($existing !== null) {
                return $existing;
            }

            $plan = $this->catalog->findVersion(
                (string) config('subscriptions.default_plan.code', 'atlas_solo'),
                (int) config('subscriptions.default_plan.version', 1),
            );
            if ($plan === null) {
                throw new \DomainException('Default subscription plan unavailable.');
            }

            $trial = Trial::start(
                id: TrialId::generate(),
                workspaceId: $workspaceId,
                planId: $plan->id,
                startedAt: $activatedAt,
                durationDays: (int) config('subscriptions.trial_days', 30),
            );

            $this->trials->save($trial);
            $this->entitlements->save(new Entitlement(
                workspaceId: $workspaceId,
                sourceType: 'Trial',
                sourceId: $trial->id()->value,
                fullCapabilities: $plan->capabilities,
                restrictedCapabilities: self::RESTRICTED_CAPABILITIES,
                limits: $plan->limits,
                validUntil: $trial->endsAt(),
                computedAt: $activatedAt,
                version: 1,
            ));

            $event = new TrialStarted(
                trialId: $trial->id(),
                workspaceId: $workspaceId,
                planId: $plan->id,
                endsAt: $trial->endsAt(),
                eventId: EventId::generate(),
                occurredAt: $activatedAt,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent(
                $event,
                correlationId: $correlationId,
                causationId: $causationId,
            ));

            return $trial;
        });
    }
}
