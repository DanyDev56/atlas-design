<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Activity;
use Atlas\Modules\Crm\Domain\Client;
use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresActivityRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresContactRepository;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final class CrmQueryHandler
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly PostgresClientRepository $clients,
        private readonly PostgresContactRepository $contacts,
        private readonly PostgresOpportunityRepository $opportunities,
        private readonly PostgresActivityRepository $activities,
    ) {}

    /** @return array<string, mixed> */
    public function getClient(string $actorUserId, string $workspaceId, string $clientId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.read');

        $client = $this->clients->findById($workspaceId, new ClientId($clientId));

        if ($client === null) {
            throw new \DomainException('Client not found.');
        }

        return $this->serializeClient($client);
    }

    /** @return list<array<string, mixed>> */
    public function listContacts(string $actorUserId, string $workspaceId, string $clientId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.contacts.read');

        $client = $this->clients->findById($workspaceId, new ClientId($clientId));

        if ($client === null) {
            throw new \DomainException('Client not found.');
        }

        return array_map(
            fn (array $row) => [
                'contact_id' => $row['id'],
                'client_id' => $row['client_id'],
                'profile' => json_decode($row['profile'], true, 512, JSON_THROW_ON_ERROR),
                'status' => $row['status'],
                'is_primary' => $row['id'] === $client->primaryContactId(),
                'version' => (int) $row['version'],
                'created_at' => $row['created_at'],
                'archived_at' => $row['archived_at'] ?? null,
            ],
            $this->contacts->listByClient($workspaceId, new ClientId($clientId)),
        );
    }

    /** @return list<array<string, mixed>> */
    public function listClients(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.read');

        return array_map(
            fn (array $row) => [
                'client_id' => $row['id'],
                'display_name' => $row['display_name'],
                'kind' => $row['kind'],
                'status' => $row['status'],
                'version' => (int) $row['version'],
                'archived_at' => $row['archived_at'] ?? null,
            ],
            $this->clients->listByWorkspace($workspaceId),
        );
    }

    /** @return list<array<string, mixed>> */
    public function listClientActivities(string $actorUserId, string $workspaceId, string $clientId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.activities.read');

        if ($this->clients->findById($workspaceId, new ClientId($clientId)) === null) {
            throw new \DomainException('Client not found.');
        }

        return array_map(
            fn (array $row) => [
                'activity_id' => $row['id'],
                'client_id' => $row['client_id'],
                'contact_id' => $row['contact_id'],
                'opportunity_id' => $row['opportunity_id'],
                'kind' => $row['kind'],
                'summary' => $row['summary'],
                'occurred_at' => $row['occurred_at'],
                'status' => $row['status'],
                'version' => (int) $row['version'],
            ],
            $this->activities->listRecordedByClient($workspaceId, new ClientId($clientId)),
        );
    }

    /** @return list<array<string, mixed>> */
    public function listClientActivityAudit(string $actorUserId, string $workspaceId, string $clientId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.activities.read');

        if ($this->clients->findById($workspaceId, new ClientId($clientId)) === null) {
            throw new \DomainException('Client not found.');
        }

        return array_map(function (array $row): array {
            $revisions = array_map(
                fn (array $revision) => [
                    'revision' => (int) $revision['revision'],
                    'kind' => $revision['kind'],
                    'summary' => $revision['summary'],
                    'occurred_at' => $revision['occurred_at'],
                    'reason' => $revision['correction_reason'],
                    'actor_user_id' => $revision['corrected_by'],
                    'corrected_at' => $revision['corrected_at'],
                ],
                $row['revisions'],
            );

            return [
                'activity_id' => $row['id'],
                'client_id' => $row['client_id'],
                'contact_id' => $row['contact_id'],
                'opportunity_id' => $row['opportunity_id'],
                'status' => $row['status'],
                'aggregate_version' => (int) $row['version'],
                'current_content' => [
                    'revision' => count($revisions) + 1,
                    'kind' => $row['kind'],
                    'summary' => $row['summary'],
                    'occurred_at' => $row['occurred_at'],
                ],
                'corrections' => $revisions,
                'removal' => $row['status'] === Activity::STATUS_REMOVED ? [
                    'reason' => $row['removal_reason'],
                    'actor_user_id' => $row['removed_by'],
                    'removed_at' => $row['removed_at'],
                ] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $this->activities->listAuditedByClient($workspaceId, new ClientId($clientId)));
    }

    /** @return array<string, mixed> */
    public function getOpportunity(string $actorUserId, string $workspaceId, string $opportunityId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.read');

        $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

        if ($opportunity === null) {
            throw new \DomainException('Opportunity not found.');
        }

        return $this->serializeOpportunity($opportunity);
    }

    /** @return list<array<string, mixed>> */
    public function listOpportunities(string $actorUserId, string $workspaceId, ?string $status = null): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.read');

        return array_map(
            fn (array $row) => [
                'opportunity_id' => $row['id'],
                'client_id' => $row['client_id'],
                'title' => $row['title'],
                'status' => $row['status'],
                'estimated_amount_cents' => $row['estimated_amount_cents'] !== null
                    ? (int) $row['estimated_amount_cents']
                    : null,
                'currency' => $row['currency'],
                'version' => (int) $row['version'],
            ],
            $this->opportunities->listByWorkspace($workspaceId, $status),
        );
    }

    /** @return array<string, mixed> */
    public function getPipeline(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.read');

        return [
            'workspace_id' => $workspaceId,
            'counts_by_status' => $this->opportunities->pipelineCounts($workspaceId),
        ];
    }

    /** @return array<string, mixed> */
    public function getClientBillingContext(string $actorUserId, string $workspaceId, string $clientId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.clients.read');

        $client = $this->clients->findById($workspaceId, new ClientId($clientId));

        if ($client === null) {
            throw new \DomainException('Client not found.');
        }

        return [
            'workspace_id' => $workspaceId,
            'client_id' => $clientId,
            'client_status' => $client->status(),
            'client_kind' => $client->kind(),
            'client_profile_version' => $client->profileVersion(),
            'client_billing_profile_version' => $client->billingProfileVersion(),
            'current_display_name' => $client->displayName(),
            'current_billing_profile' => $client->billingProfile(),
        ];
    }

    /** @return array<string, mixed> */
    public function getOpportunityCommercialContext(
        string $actorUserId,
        string $workspaceId,
        string $opportunityId,
    ): array {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'crm.opportunities.read');

        $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

        if ($opportunity === null) {
            throw new \DomainException('Opportunity not found.');
        }

        return [
            'workspace_id' => $workspaceId,
            'opportunity_id' => $opportunityId,
            'client_id' => $opportunity->clientId()->value,
            'opportunity_status' => $opportunity->status(),
            'opportunity_version' => $opportunity->version(),
            'title' => $opportunity->title(),
            'estimated_amount_cents' => $opportunity->estimatedAmountCents(),
            'contact_id' => $opportunity->contactId(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeClient(Client $client): array
    {
        return [
            'client_id' => $client->id()->value,
            'workspace_id' => $client->workspaceId(),
            'kind' => $client->kind(),
            'display_name' => $client->displayName(),
            'profile' => $client->profile(),
            'billing_profile' => $client->billingProfile(),
            'status' => $client->status(),
            'primary_contact_id' => $client->primaryContactId(),
            'profile_version' => $client->profileVersion(),
            'billing_profile_version' => $client->billingProfileVersion(),
            'version' => $client->version(),
            'archived_at' => $client->archivedAt()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeOpportunity(Opportunity $opportunity): array
    {
        return [
            'opportunity_id' => $opportunity->id()->value,
            'workspace_id' => $opportunity->workspaceId(),
            'client_id' => $opportunity->clientId()->value,
            'contact_id' => $opportunity->contactId(),
            'title' => $opportunity->title(),
            'estimated_amount_cents' => $opportunity->estimatedAmountCents(),
            'currency' => $opportunity->currency(),
            'status' => $opportunity->status(),
            'version' => $opportunity->version(),
            'qualified_at' => $opportunity->qualifiedAt()?->format(DATE_ATOM),
            'loss_reason_code' => $opportunity->lossReasonCode(),
            'loss_note' => $opportunity->lossNote(),
            'lost_at' => $opportunity->lostAt()?->format(DATE_ATOM),
        ];
    }
}
