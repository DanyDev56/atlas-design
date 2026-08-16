<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class ManualWinOpportunityTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_owner_wins_qualified_opportunity_manually_and_publishes_analytics_fact(): void
    {
        $owner = $this->onboardOwner($this);
        $headers = ['Authorization' => 'Bearer '.$owner['token']];
        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Mission gagnée',
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $client->json('client_id'),
            'title' => 'Accompagnement stratégique',
            'estimated_amount_cents' => 450000,
            'currency' => 'EUR',
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $opportunityId = $opportunity->json('opportunity_id');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/win",
            ['result' => ['source' => 'Manual'], 'expected_revision' => 1],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Opportunity is not qualified.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify",
            ['expected_revision' => 1],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertOk()->assertJsonPath('version', 2);

        $payload = ['result' => ['source' => 'Manual'], 'expected_revision' => 2];
        $idempotencyKey = (string) Str::uuid();
        $won = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/win",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()
            ->assertJsonPath('opportunity_id', $opportunityId)
            ->assertJsonPath('status', 'Won')
            ->assertJsonPath('version', 3)
            ->assertJsonPath('win_source', 'Manual')
            ->assertJsonPath('won_at', fn ($value) => is_string($value) && $value !== '');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/win",
            $payload,
            $headers + ['Idempotency-Key' => $idempotencyKey],
        )->assertOk()->assertExactJson($won->json());

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}",
            $headers,
        )->assertOk()
            ->assertJsonPath('status', 'Won')
            ->assertJsonPath('win_source', 'Manual')
            ->assertJsonPath('won_quote_id', null)
            ->assertJsonPath('won_by', $owner['user_id'])
            ->assertJsonPath('won_at', fn ($value) => is_string($value) && $value !== '');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/win",
            ['result' => ['source' => 'Manual'], 'expected_revision' => 3],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Opportunity is not qualified.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/lose",
            ['loss_reason_code' => 'Other', 'expected_revision' => 3],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertStatus(422)
            ->assertJsonPath('messages.0', 'Opportunity is terminal.');

        $row = DB::table('crm.opportunities')->where('id', $opportunityId)->sole();
        $this->assertSame('Manual', $row->win_source);
        $this->assertNull($row->won_quote_id);
        $this->assertSame($owner['user_id'], $row->won_by);
        $this->assertNotNull($row->won_at);

        $event = DB::table('platform.outbox_messages')
            ->where('event_type', 'crm.opportunity_won')
            ->sole();
        $eventPayload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Manual', $eventPayload['source']);
        $this->assertSame(3, $eventPayload['version']);
        $this->assertNull($eventPayload['quote_id']);
        $this->assertArrayNotHasKey('won_by', $eventPayload);

        app(OutboxProcessor::class)->processPending();
        $fact = DB::table('analytics.source_facts')
            ->where('workspace_id', $owner['workspace_id'])
            ->where('source_event_type', 'crm.opportunity_won')
            ->sole();
        $this->assertSame(3, (int) $fact->aggregate_version);
        $this->assertSame('Won', json_decode($fact->payload, true, 512, JSON_THROW_ON_ERROR)['status']);
    }
}
