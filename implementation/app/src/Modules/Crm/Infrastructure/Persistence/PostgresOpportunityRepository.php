<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Atlas\Modules\Crm\Domain\Opportunity;
use Atlas\Modules\Crm\Domain\OpportunityId;
use Illuminate\Support\Facades\DB;

final class PostgresOpportunityRepository
{
    public function insert(Opportunity $opportunity): void
    {
        DB::table('crm.opportunities')->insert([
            'id' => $opportunity->id()->value,
            'workspace_id' => $opportunity->workspaceId(),
            'client_id' => $opportunity->clientId()->value,
            'contact_id' => $opportunity->contactId(),
            'title' => $opportunity->title(),
            'estimated_amount_cents' => $opportunity->estimatedAmountCents(),
            'currency' => $opportunity->currency(),
            'status' => $opportunity->status(),
            'version' => $opportunity->version(),
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'qualified_at' => null,
        ]);
    }

    public function update(Opportunity $opportunity): void
    {
        DB::table('crm.opportunities')
            ->where('id', $opportunity->id()->value)
            ->update([
                'status' => $opportunity->status(),
                'version' => $opportunity->version(),
                'updated_at' => now()->toIso8601String(),
                'qualified_at' => $opportunity->qualifiedAt()?->format('Y-m-d H:i:sP'),
            ]);
    }

    public function findById(string $workspaceId, OpportunityId $id): ?Opportunity
    {
        $row = DB::table('crm.opportunities')
            ->where('id', $id->value)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row !== null ? Opportunity::reconstitute((array) $row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listByWorkspace(string $workspaceId, ?string $status = null): array
    {
        $query = DB::table('crm.opportunities')
            ->where('workspace_id', $workspaceId)
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map(fn ($row) => (array) $row)->all();
    }

    /** @return array<string, int> */
    public function pipelineCounts(string $workspaceId): array
    {
        $rows = DB::table('crm.opportunities')
            ->selectRaw('status, COUNT(*) as total')
            ->where('workspace_id', $workspaceId)
            ->groupBy('status')
            ->get();

        $counts = [
            Opportunity::STATUS_OPEN => 0,
            Opportunity::STATUS_QUALIFIED => 0,
            Opportunity::STATUS_WON => 0,
            Opportunity::STATUS_LOST => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->total;
        }

        return $counts;
    }

    public function hasNonTerminalForContact(string $workspaceId, string $clientId, string $contactId): bool
    {
        return DB::table('crm.opportunities')
            ->where('workspace_id', $workspaceId)
            ->where('client_id', $clientId)
            ->where('contact_id', $contactId)
            ->whereIn('status', [Opportunity::STATUS_OPEN, Opportunity::STATUS_QUALIFIED])
            ->exists();
    }
}
