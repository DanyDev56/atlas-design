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
            ->assertJsonPath('cards.2.status', 'NotCollected')
            ->assertJsonPath('cards.2.values', []);

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
