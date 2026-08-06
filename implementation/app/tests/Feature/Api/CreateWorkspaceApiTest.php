<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Atlas\Platform\Support\CorrelationId;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class CreateWorkspaceApiTest extends IntegrationTestCase
{
    public function test_creates_workspace_via_spike_api(): void
    {
        $response = $this->postJson('/api/spike/workspaces', [
            'name' => 'API Workspace',
        ], [
            'X-Correlation-Id' => '11111111-1111-4111-8111-111111111111',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'Provisioning')
            ->assertJsonPath('access_state', 'Restricted');

        $workspaceId = $response->json('workspace_id');

        $this->assertTrue(
            \Illuminate\Support\Facades\DB::table('platform.outbox_messages')
                ->where('event_id', $response->json('event_id'))
                ->where('correlation_id', '11111111-1111-4111-8111-111111111111')
                ->exists()
        );
    }

    public function test_maps_arbitrary_correlation_header_to_uuid_for_outbox(): void
    {
        $traceKey = 'trace-manuelle-001';
        $expectedCorrelationId = CorrelationId::resolve($traceKey);

        $response = $this->postJson('/api/spike/workspaces', [
            'name' => 'Trace Workspace',
        ], [
            'X-Correlation-Id' => $traceKey,
        ]);

        $response->assertCreated()
            ->assertHeader('X-Correlation-Id', $expectedCorrelationId);

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_id', $response->json('event_id'))
                ->where('correlation_id', $expectedCorrelationId)
                ->exists(),
        );
    }

    public function test_rejects_unknown_fields(): void
    {
        $response = $this->postJson('/api/spike/workspaces', [
            'name' => 'Valid',
            'unexpected' => 'field',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'ValidationError');
    }

    public function test_rejects_invalid_name(): void
    {
        $response = $this->postJson('/api/spike/workspaces', [
            'name' => 'A',
        ]);

        $response->assertStatus(422);
    }
}
