<?php

declare(strict_types=1);

namespace Atlas\Modules\Crm\Infrastructure\Persistence;

use Atlas\Modules\Crm\Domain\ClientId;
use Atlas\Modules\Crm\Domain\Contact;
use Atlas\Modules\Crm\Domain\ContactId;
use Illuminate\Support\Facades\DB;

final class PostgresContactRepository
{
    public function insertWithWorkspace(Contact $contact, string $workspaceId): void
    {
        DB::table('crm.contacts')->insert([
            'id' => $contact->id()->value,
            'workspace_id' => $workspaceId,
            'client_id' => $contact->clientId()->value,
            'profile' => json_encode($contact->profile(), JSON_THROW_ON_ERROR),
            'status' => 'Active',
            'version' => 1,
            'created_at' => now()->toIso8601String(),
        ]);
    }

    public function findById(string $workspaceId, ClientId $clientId, ContactId $contactId): ?Contact
    {
        $row = DB::table('crm.contacts')
            ->where('id', $contactId->value)
            ->where('workspace_id', $workspaceId)
            ->where('client_id', $clientId->value)
            ->first();

        return $row !== null ? Contact::reconstitute((array) $row) : null;
    }
}
