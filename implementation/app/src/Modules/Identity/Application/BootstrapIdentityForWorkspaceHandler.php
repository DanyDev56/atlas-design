<?php

declare(strict_types=1);

namespace Atlas\Modules\Identity\Application;

use Atlas\Modules\Identity\Domain\MembershipCreated;
use Atlas\Modules\Identity\Domain\MembershipId;
use Atlas\Modules\Identity\Domain\RoleId;
use Atlas\Modules\Identity\Domain\UserId;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresMembershipRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresRoleRepository;
use Atlas\Modules\Identity\Infrastructure\Persistence\PostgresUserRepository;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;

final class BootstrapIdentityForWorkspaceHandler
{
    private const OWNER_PERMISSIONS = [
        'workspace.members.read',
        'workspace.members.create',
        'workspace.members.invite',
        'workspace.members.change-role',
        'workspace.members.remove',
        'workspace.settings.read',
        'workspace.settings.update',
        'crm.clients.read',
        'crm.clients.create',
        'crm.clients.update-profile',
        'crm.clients.update-billing-profile',
        'crm.clients.archive',
        'crm.clients.reactivate',
        'crm.contacts.read',
        'crm.contacts.create',
        'crm.contacts.update',
        'crm.contacts.change-primary',
        'crm.contacts.archive',
        'crm.contacts.reactivate',
        'crm.opportunities.read',
        'crm.opportunities.create',
        'crm.opportunities.update',
        'crm.opportunities.qualify',
        'crm.opportunities.win',
        'crm.opportunities.win-from-quote',
        'crm.opportunities.lose',
        'crm.activities.read',
        'crm.activities.record',
        'crm.activities.correct',
        'billing.quotes.read',
        'billing.quotes.create',
        'billing.quotes.update-draft',
        'billing.quotes.send',
        'billing.quotes.withdraw',
        'billing.invoices.read',
        'billing.invoices.create',
        'billing.invoices.update-draft',
        'billing.invoices.discard',
        'billing.invoices.issue',
        'billing.invoices.correct-metadata',
        'billing.invoices.send',
        'billing.invoices.remind',
        'billing.payments.read',
        'billing.payments.record',
        'billing.payments.reverse',
        'analytics.metrics.read',
        'analytics.facts.ingest',
        'analytics.projections.rebuild',
        'analytics.snapshots.publish',
        'analytics.snapshots.consume',
        'crm.analytics-facts.read',
        'billing.analytics-facts.read',
        'business-health.assessments.read',
        'advisor.recommendations.read',
        'advisor.recommendations.complete',
        'advisor.recommendations.dismiss',
        'notifications.inbox.read',
        'notifications.inbox.mark-read',
        'notifications.preferences.read',
        'notifications.preferences.change',
    ];

    public function __construct(
        private readonly PostgresUserRepository $users,
        private readonly PostgresRoleRepository $roles,
        private readonly PostgresMembershipRepository $memberships,
        private readonly OutboxWriter $outbox,
    ) {}

    /** @return array{role_id: string, membership_id: string} */
    public function handle(string $userId, string $workspaceId): array
    {
        return DB::transaction(function () use ($userId, $workspaceId): array {
            $user = $this->users->findById(new UserId($userId));

            if ($user === null || ! $user->canAuthenticate()) {
                throw new \DomainException('User unavailable.');
            }

            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $roleIdValue = $this->roles->findOwnerRoleId($workspaceId);

            if ($roleIdValue === null) {
                $roleId = RoleId::generate();
                $this->roles->createOwnerRole($roleId, $workspaceId, self::OWNER_PERMISSIONS, $now);
                $roleIdValue = $roleId->value;
            } else {
                $roleId = new RoleId($roleIdValue);
            }

            $existing = $this->memberships->findByUserAndWorkspace(new UserId($userId), $workspaceId);

            if ($existing !== null) {
                return [
                    'role_id' => $roleIdValue,
                    'membership_id' => $existing['id'],
                ];
            }

            $membershipId = MembershipId::generate();
            $this->memberships->create(
                membershipId: $membershipId,
                userId: new UserId($userId),
                workspaceId: $workspaceId,
                roleId: $roleId,
                now: $now,
            );

            $event = new MembershipCreated(
                membershipId: $membershipId,
                userId: new UserId($userId),
                workspaceId: $workspaceId,
                roleId: $roleId,
                eventId: EventId::generate(),
                occurredAt: $now,
            );
            $this->outbox->append(OutgoingMessage::fromDomainEvent($event));

            return [
                'role_id' => $roleIdValue,
                'membership_id' => $membershipId->value,
            ];
        });
    }
}
