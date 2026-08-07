<?php

declare(strict_types=1);

namespace Tests\Integration\Crm;

use Atlas\Modules\Crm\Application\CreateClientHandler;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class CrmOutboxIntegrationTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_create_client_persists_outbox_atomically(): void
    {
        $owner = $this->onboardOwner($this, 'outbox@crm.test');

        $handler = app(CreateClientHandler::class);
        $result = $handler->handle(
            actorUserId: $owner['user_id'],
            workspaceId: $owner['workspace_id'],
            kind: 'Individual',
            displayName: 'Outbox Client',
            profile: [],
            billingProfile: null,
            requestId: (string) \Illuminate\Support\Str::uuid(),
        );

        $this->assertTrue(
            DB::table('crm.clients')->where('id', $result['client_id'])->exists()
        );

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'crm.client_created')
                ->where('payload->client_id', $result['client_id'])
                ->exists()
        );
    }
}
