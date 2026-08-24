<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorOverviewTest extends IntegrationTestCase
{
    public function test_overview_and_filtered_registries_use_real_sources_without_exposing_sensitive_fields(): void
    {
        $token = $this->operatorToken([
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::OUTBOX_READ,
            OperatorPermissionCatalog::EMAIL_READ,
            OperatorPermissionCatalog::SUBSCRIPTIONS_READ,
        ]);
        $pendingEventId = $this->insertOutbox('billing.invoice_delivery_requested', attempts: 1);
        $acceptedEventId = $this->insertOutbox('billing.quote_delivery_requested', dispatched: true);
        DB::table('platform.email_deliveries')->insert([
            'event_id' => $acceptedEventId,
            'event_type' => 'billing.quote_delivery_requested',
            'template_key' => 'billing.quote.sent',
            'template_version' => '1',
            'recipient_fingerprint' => 'secret-fingerprint',
            'status' => 'Accepted',
            'provider' => 'array',
            'provider_message_id' => 'secret-provider-id',
            'accepted_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($token)->getJson('/api/operator/overview')
            ->assertOk()
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('actions_enabled', false)
            ->assertJsonPath('cards.0.status', 'Available')
            ->assertJsonPath('cards.0.values.1.value', 1)
            ->assertJsonPath('cards.1.values.0.value', 1)
            ->assertJsonPath('cards.2.status', 'Available')
            ->assertJsonPath('cards.3.status', 'NotCollected')
            ->assertJsonPath('cards.3.values', []);

        $outbox = $this->withToken($token)->getJson('/api/operator/overview/outbox?status=Retrying&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.event_id', $pendingEventId)
            ->assertJsonMissingPath('items.0.payload')
            ->assertJsonMissingPath('items.0.last_error');
        self::assertArrayNotHasKey('payload', $outbox->json('items.0'));

        $this->withToken($token)->getJson('/api/operator/overview/emails?status=Accepted&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.event_id', $acceptedEventId)
            ->assertJsonMissingPath('items.0.recipient_fingerprint')
            ->assertJsonMissingPath('items.0.provider_message_id');
    }

    public function test_subscription_and_webhook_registries_separate_environments_and_hide_provider_references(): void
    {
        $token = $this->operatorToken([
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::SUBSCRIPTIONS_READ,
        ], 'billing-operator@example.test');
        $workspaceId = (string) Str::uuid();
        $providerSubscriptionReference = 'sub_live_sensitive_reference';
        $providerEventId = 'evt_live_sensitive_reference';
        $now = now('UTC');

        DB::table('subscriptions.recurring_subscriptions')->insert([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'plan_id' => 'c7ab1c54-33cf-5c00-9de2-5ae6d2c7da18',
            'plan_price_id' => 'a5d9e095-bcee-54ef-8b40-f47256997d40',
            'provider' => 'stripe',
            'billing_environment' => 'Live',
            'provider_subscription_reference' => $providerSubscriptionReference,
            'status' => 'PastDue',
            'current_period_start' => $now->copy()->subMonth(),
            'current_period_end' => $now->copy()->addMonth(),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'past_due_since' => $now->copy()->subDay(),
            'last_provider_event_at' => $now,
            'version' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('subscriptions.webhook_inbox')->insert([
            'id' => (string) Str::uuid(),
            'provider' => 'stripe',
            'billing_environment' => 'Live',
            'provider_event_id' => $providerEventId,
            'event_type' => 'PaymentFailed',
            'provider_subscription_reference' => $providerSubscriptionReference,
            'payload_hash' => hash('sha256', 'sensitive payload'),
            'payload' => json_encode(['customer_email' => 'hidden@example.test'], JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'status' => 'Failed',
            'attempts' => 2,
            'failure_reason' => 'Sensitive provider failure',
            'received_at' => $now,
            'processed_at' => null,
        ]);

        $overview = $this->withToken($token)->getJson('/api/operator/overview')->assertOk();
        $overview->assertJsonPath('cards.2.values.2.value', 2)
            ->assertJsonPath('cards.2.tone', 'Critical');

        $subscriptions = $this->withToken($token)->getJson('/api/operator/overview/subscriptions?status=PastDue&environment=Live')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.billing_environment', 'Live')
            ->assertJsonPath('items.0.status', 'PastDue')
            ->assertJsonMissingPath('items.0.workspace_id')
            ->assertJsonMissingPath('items.0.provider_subscription_reference');
        self::assertStringStartsWith('WS-', (string) $subscriptions->json('items.0.workspace_reference'));
        self::assertStringNotContainsString($workspaceId, $subscriptions->getContent());
        self::assertStringNotContainsString($providerSubscriptionReference, $subscriptions->getContent());

        $webhooks = $this->withToken($token)->getJson('/api/operator/overview/subscription-webhooks?status=Failed&environment=Live')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.billing_environment', 'Live')
            ->assertJsonMissingPath('items.0.payload')
            ->assertJsonMissingPath('items.0.failure_reason')
            ->assertJsonMissingPath('items.0.provider_event_id');
        self::assertStringStartsWith('WH-', (string) $webhooks->json('items.0.reference'));
        self::assertStringNotContainsString($providerEventId, $webhooks->getContent());
    }

    public function test_detail_permission_is_enforced_and_denial_is_audited(): void
    {
        $token = $this->operatorToken(OperatorPermissionCatalog::initialReadOnly(), 'limited-operator@example.test');

        $this->withToken($token)->getJson('/api/operator/overview')->assertOk();
        $this->withToken($token)->getJson('/api/operator/overview/outbox')
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');

        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.permission.denied',
            'result' => 'Denied',
            'permission' => OperatorPermissionCatalog::OUTBOX_READ,
        ]);
    }

    public function test_runtime_heartbeats_and_maintenance_results_are_exposed_without_raw_diagnostics(): void
    {
        $token = $this->operatorToken(OperatorPermissionCatalog::initialReadOnly(), 'runtime-operator@example.test');
        $this->artisan('atlas:operations:heartbeat', ['role' => 'worker'])->assertSuccessful();
        $this->artisan('atlas:operations:heartbeat', ['role' => 'scheduler'])->assertSuccessful();
        $this->artisan('atlas:operations:record-maintenance', [
            'kind' => 'Backup',
            'status' => 'Succeeded',
            'reference' => 'atlas-20260824T120000Z',
            '--started-at' => '2026-08-24T11:59:00+00:00',
            '--size-bytes' => 1048576,
        ])->assertSuccessful();

        $this->withToken($token)->getJson('/api/operator/overview/runtime')
            ->assertOk()
            ->assertJsonPath('database_available', true)
            ->assertJsonPath('roles.api.status', 'Current')
            ->assertJsonPath('roles.worker.status', 'Current')
            ->assertJsonPath('roles.scheduler.status', 'Current');

        $this->withToken($token)->getJson('/api/operator/overview/maintenance')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.kind', 'Backup')
            ->assertJsonPath('items.0.status', 'Succeeded')
            ->assertJsonPath('items.0.size_bytes', 1048576)
            ->assertJsonMissingPath('items.0.path')
            ->assertJsonMissingPath('items.0.error');
    }

    /** @param list<string> $permissions */
    private function operatorToken(array $permissions, string $email = 'overview-operator@example.test'): string
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Overview Operator',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $this->postJson('/api/auth/verify-email', [
            'user_id' => (string) $register->json('user_id'),
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Prepare operator overview test',
        ])->assertSuccessful();

        $login = $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();

        return (string) $login->json('token');
    }

    private function insertOutbox(string $eventType, int $attempts = 0, bool $dispatched = false): string
    {
        $eventId = (string) Str::uuid();
        DB::table('platform.outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => json_encode(['sensitive' => 'hidden'], JSON_THROW_ON_ERROR),
            'occurred_at' => now()->subMinute(),
            'schema_version' => 1,
            'created_at' => now()->subMinute(),
            'dispatched_at' => $dispatched ? now() : null,
            'attempts' => $attempts,
            'available_at' => now()->subMinute(),
            'last_error' => $attempts > 0 ? 'secret error' : null,
            'failed_at' => null,
        ]);

        return $eventId;
    }
}
