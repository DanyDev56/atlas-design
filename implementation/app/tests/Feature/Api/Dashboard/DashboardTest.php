<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Dashboard;

use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class DashboardTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_dashboard_composes_public_reads(): void
    {
        $owner = $this->onboardOwner($this, 'dashboard@test');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/dashboard", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonStructure([
                'workspace_id',
                'advisor_priority' => ['source_domain', 'data_state', 'payload'],
                'business_health' => ['source_domain', 'data_state', 'payload'],
                'pipeline' => ['source_domain', 'data_state', 'payload'],
                'billing' => ['source_domain', 'data_state', 'payload'],
                'notifications' => ['source_domain', 'data_state', 'payload'],
            ]);
    }
}
