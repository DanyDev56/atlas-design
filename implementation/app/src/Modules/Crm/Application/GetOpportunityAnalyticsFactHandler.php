<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Application;

use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresOpportunityRepository;
use Illuminate\Support\Facades\DB;

final class GetOpportunityAnalyticsFactHandler
{
    public function __construct(
        private readonly PostgresOpportunityRepository $opportunities,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $workspaceId, string $opportunityId, int $aggregateVersion): array
    {
        $opportunity = $this->opportunities->findById($workspaceId, new OpportunityId($opportunityId));

        if ($opportunity === null || $opportunity->version() !== $aggregateVersion) {
            throw new \DomainException('Opportunity not found.');
        }

        $row = DB::table('crm.opportunities')
            ->where('id', $opportunityId)
            ->first();

        $fact = [
            'workspace_id' => $workspaceId,
            'opportunity_id' => $opportunityId,
            'aggregate_version' => $aggregateVersion,
            'client_id' => $opportunity->clientId()->value,
            'status' => $opportunity->status(),
            'estimated_amount_cents' => $opportunity->estimatedAmountCents(),
            'currency' => $opportunity->currency(),
            'created_at' => $row?->created_at,
            'qualified_at' => $row?->qualified_at,
            'closed_at' => in_array($opportunity->status(), [Opportunity::STATUS_WON, Opportunity::STATUS_LOST], true)
                ? $row?->updated_at
                : null,
        ];
        $fact['fact_hash'] = hash('sha256', json_encode($fact, JSON_THROW_ON_ERROR));

        return $fact;
    }
}
