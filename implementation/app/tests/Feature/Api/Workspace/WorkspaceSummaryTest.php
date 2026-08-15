<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Workspace;

use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class WorkspaceSummaryTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_can_read_the_authoritative_workspace_summary(): void
    {
        $owner = $this->onboardOwner($this, 'workspace-summary@test');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/summary", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('workspace_id', $owner['workspace_id'])
            ->assertJsonPath('display_name', 'CRM Workspace')
            ->assertJsonPath('access_state', 'Active')
            ->assertJsonStructure(['version']);
    }

    public function test_summary_does_not_cross_workspace_membership_boundary(): void
    {
        $first = $this->onboardOwner($this, 'workspace-summary-first@test');
        $second = $this->onboardOwner($this, 'workspace-summary-second@test');

        $this->getJson("/api/workspaces/{$second['workspace_id']}/summary", [
            'Authorization' => 'Bearer '.$first['token'],
        ])->assertForbidden();
    }
}
