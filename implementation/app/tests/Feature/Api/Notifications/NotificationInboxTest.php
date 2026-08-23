<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Notifications;

use Atlas\Modules\Notifications\Domain\NotificationPolicy;
use Atlas\Modules\Notifications\Infrastructure\Persistence\PostgresNotificationRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class NotificationInboxTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_unread_count_includes_resolved_notifications_that_are_still_unread(): void
    {
        $owner = $this->onboardOwner($this, 'notification-count@test');
        $notifications = app(PostgresNotificationRepository::class);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $resolvedNotificationId = $notifications->create(
            workspaceId: $owner['workspace_id'],
            recipientUserId: $owner['user_id'],
            recommendationId: null,
            priority: 'High',
            content: ['recommendation_key' => 'advisor.collect-overdue-invoices'],
            channels: [NotificationPolicy::CHANNEL_IN_APP],
            createdAt: $now->modify('-1 minute'),
            displayUntil: null,
        );
        $notifications->resolveActiveForRecipient($owner['workspace_id'], $owner['user_id']);
        $notifications->create(
            workspaceId: $owner['workspace_id'],
            recipientUserId: $owner['user_id'],
            recommendationId: null,
            priority: 'High',
            content: ['recommendation_key' => 'advisor.collect-overdue-invoices'],
            channels: [NotificationPolicy::CHANNEL_IN_APP],
            createdAt: $now,
            displayUntil: null,
        );

        $headers = ['Authorization' => 'Bearer '.$owner['token']];
        $this->getJson("/api/workspaces/{$owner['workspace_id']}/notifications/unread-count", $headers)
            ->assertOk()
            ->assertJsonPath('unread_count', 2);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/notifications/{$resolvedNotificationId}/mark-read",
            ['expected_revision' => 1],
            $headers,
        )->assertOk();

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/notifications/unread-count", $headers)
            ->assertOk()
            ->assertJsonPath('unread_count', 1);
    }

    public function test_advisor_overview_change_creates_in_app_notification(): void
    {
        $owner = $this->onboardOwner($this, 'notifications@test');

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Notify Client',
            'profile' => [],
            'billing_profile' => ['billing_email' => 'factures@notify-client.test'],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $clientId = $client->json('client_id');

        $opportunity = $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities", [
            'client_id' => $clientId,
            'title' => 'Notify Opp',
            'estimated_amount_cents' => 50000,
            'currency' => 'EUR',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $opportunityId = $opportunity->json('opportunity_id');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/opportunities/{$opportunityId}/qualify", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $quote = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes", [
            'client_id' => $clientId,
            'opportunity_id' => $opportunityId,
            'currency' => 'EUR',
            'lines' => [
                ['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => 50000],
            ],
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $quoteId = $quote->json('quote_id');

        $sent = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/send", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/public/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/accept", [
            'public_token' => $sent->json('public_accept_token'),
            'expected_revision' => 2,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        app(OutboxProcessor::class)->processPending();

        $invoice = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes/{$quoteId}/invoices", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $invoiceId = $invoice->json('invoice_id');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/issue", [
            'expected_revision' => 1,
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertOk();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/payments", [
            'amount_cents' => 50000,
            'reference' => 'VIR-NOTIFY',
        ], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();
        $this->postJson("/api/workspaces/{$owner['workspace_id']}/analytics/snapshots/publish", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();
        app(OutboxProcessor::class)->processPending();

        $this->assertTrue(
            DB::table('platform.outbox_messages')
                ->where('event_type', 'advisor.overview_changed')
                ->exists()
        );

        $unread = $this->getJson("/api/workspaces/{$owner['workspace_id']}/notifications/unread-count", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk();

        $this->assertGreaterThanOrEqual(0, $unread->json('unread_count'));

        $list = $this->getJson("/api/workspaces/{$owner['workspace_id']}/notifications", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonStructure(['notifications']);

        if ($unread->json('unread_count') > 0) {
            $notificationId = $list->json('notifications.0.notification_id');
            $revision = $list->json('notifications.0.revision');

            $this->postJson("/api/workspaces/{$owner['workspace_id']}/notifications/{$notificationId}/mark-read", [
                'expected_revision' => $revision,
            ], [
                'Authorization' => 'Bearer '.$owner['token'],
                'Idempotency-Key' => (string) Str::uuid(),
            ])->assertOk();
        }
    }
}
