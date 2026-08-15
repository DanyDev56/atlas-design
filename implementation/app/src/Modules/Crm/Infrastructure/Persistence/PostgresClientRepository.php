<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Atlas\Modules\Crm\Domain\Client;
use Atlas\Modules\Crm\Domain\ClientId;
use Illuminate\Support\Facades\DB;

final class PostgresClientRepository
{
    public function insert(Client $client): void
    {
        DB::table('crm.clients')->insert([
            'id' => $client->id()->value,
            'workspace_id' => $client->workspaceId(),
            'kind' => $client->kind(),
            'display_name' => $client->displayName(),
            'profile' => json_encode($client->profile(), JSON_THROW_ON_ERROR),
            'billing_profile' => json_encode($client->billingProfile(), JSON_THROW_ON_ERROR),
            'status' => $client->status(),
            'primary_contact_id' => $client->primaryContactId(),
            'profile_version' => $client->profileVersion(),
            'billing_profile_version' => $client->billingProfileVersion(),
            'version' => $client->version(),
            'created_at' => $client->createdAt()->format('Y-m-d H:i:sP'),
            'updated_at' => $client->updatedAt()->format('Y-m-d H:i:sP'),
            'archive_reason' => null,
            'archived_at' => null,
        ]);
    }

    public function update(Client $client): void
    {
        DB::table('crm.clients')
            ->where('id', $client->id()->value)
            ->update([
                'display_name' => $client->displayName(),
                'profile' => json_encode($client->profile(), JSON_THROW_ON_ERROR),
                'billing_profile' => json_encode($client->billingProfile(), JSON_THROW_ON_ERROR),
                'status' => $client->status(),
                'primary_contact_id' => $client->primaryContactId(),
                'profile_version' => $client->profileVersion(),
                'billing_profile_version' => $client->billingProfileVersion(),
                'version' => $client->version(),
                'updated_at' => $client->updatedAt()->format('Y-m-d H:i:sP'),
                'archive_reason' => $client->archiveReason(),
                'archived_at' => $client->archivedAt()?->format('Y-m-d H:i:sP'),
            ]);
    }

    public function findById(string $workspaceId, ClientId $id): ?Client
    {
        $row = DB::table('crm.clients')
            ->where('id', $id->value)
            ->where('workspace_id', $workspaceId)
            ->first();

        return $row !== null ? Client::reconstitute((array) $row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listByWorkspace(string $workspaceId): array
    {
        return DB::table('crm.clients')
            ->where('workspace_id', $workspaceId)
            ->orderBy('display_name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
