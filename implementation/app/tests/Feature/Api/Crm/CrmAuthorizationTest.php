<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class CrmAuthorizationTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_user_without_membership_cannot_create_client(): void
    {
        $owner = $this->onboardOwner($this, 'owner-a@crm.test');
        $intruder = $this->onboardOwner($this, 'owner-b@crm.test');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Individual',
            'display_name' => 'Blocked',
        ], [
            'Authorization' => 'Bearer '.$intruder['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertForbidden();
    }
}
