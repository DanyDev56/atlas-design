<?php

declare(strict_types=1);

namespace Tests\Integration\Mail;

use Atlas\Modules\Billing\Domain\InvoiceDeliveryRequested;
use Atlas\Modules\Billing\Domain\InvoiceId;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresDocumentArtifactRepository;
use Atlas\Platform\Mail\TransactionalEmailSender;
use Atlas\Platform\Messaging\EventId;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Atlas\Platform\Messaging\OutboxWriter;
use Atlas\Platform\Messaging\OutgoingMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\FakeTransactionalEmailSender;

final class TransactionalEmailOutboxTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    private FakeTransactionalEmailSender $emails;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emails = new FakeTransactionalEmailSender;
        $this->app->instance(TransactionalEmailSender::class, $this->emails);
        $this->app->forgetInstance(OutboxProcessor::class);
    }

    public function test_registration_sends_verification_email_without_secret_in_outbox(): void
    {
        config()->set('platform.development.debug_verification_tokens', false);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'mail-registration@test.local',
            'display_name' => 'Mail Registration',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonMissingPath('verification_token');

        $row = DB::table('platform.outbox_messages')
            ->where('event_type', 'identity.email_verification_send_requested')
            ->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('mail-registration@test.local', (string) $row->payload);
        $this->assertStringNotContainsString('token', json_encode($response->json(), JSON_THROW_ON_ERROR));

        $this->app->make(OutboxProcessor::class)->processPending(20);

        $this->assertCount(1, $this->emails->sent);
        $email = $this->emails->sent[0];
        $this->assertSame('mail-registration@test.local', $email->recipient);
        $this->assertStringContainsString('/app/verify-email?', $email->text);
        $this->assertDatabaseHas('platform.email_deliveries', [
            'event_id' => $row->event_id,
            'status' => 'Accepted',
            'provider' => 'fake-smtp',
        ]);

        $this->app->make(OutboxProcessor::class)->processPending(20);
        $this->assertCount(1, $this->emails->sent);
    }

    public function test_invoice_delivery_uses_snapshot_endpoint_and_attaches_pdf(): void
    {
        $workspaceId = (string) Str::uuid();
        $invoiceId = (string) Str::uuid();
        $now = now();

        DB::table('workspace.workspaces')->insert([
            'id' => $workspaceId,
            'name' => 'Atelier Atlas',
            'status' => 'Active',
            'access_state' => 'Active',
            'version' => 1,
            'governance_version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('billing.invoices')->insert([
            'id' => $invoiceId,
            'workspace_id' => $workspaceId,
            'client_id' => (string) Str::uuid(),
            'quote_id' => null,
            'status' => 'Issued',
            'settlement_status' => 'Unpaid',
            'invoice_number' => 'FAC-2026-0001',
            'lines' => json_encode([['description' => 'Conseil', 'quantity' => 1, 'unit_price_cents' => 12500]], JSON_THROW_ON_ERROR),
            'total_cents' => 12500,
            'balance_cents' => 12500,
            'currency' => 'EUR',
            'client_snapshot' => json_encode([
                'display_name' => 'Client email',
                'billing_profile' => ['billing_email' => 'factures@client.test'],
            ], JSON_THROW_ON_ERROR),
            'version' => 2,
            'created_at' => $now,
            'updated_at' => $now,
            'issued_at' => $now,
            'due_date' => $now->copy()->addDays(30),
            'is_historical_import' => false,
        ]);
        $this->app->make(PostgresDocumentArtifactRepository::class)->store(
            $workspaceId,
            'invoice',
            $invoiceId,
            2,
            'facture-FAC-2026-0001.pdf',
            '%PDF-test',
        );

        $event = new InvoiceDeliveryRequested(
            invoiceId: new InvoiceId($invoiceId),
            workspaceId: $workspaceId,
            documentVersion: 2,
            eventId: EventId::generate(),
            occurredAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        DB::transaction(fn () => $this->app->make(OutboxWriter::class)->append(
            OutgoingMessage::fromDomainEvent($event),
        ));

        $this->app->make(OutboxProcessor::class)->processPending(20);

        $this->assertCount(1, $this->emails->sent);
        $email = $this->emails->sent[0];
        $this->assertSame('factures@client.test', $email->recipient);
        $this->assertSame('application/pdf', $email->attachments[0]->mediaType);
        $this->assertSame('%PDF-test', $email->attachments[0]->content);
        $this->assertDatabaseHas('platform.email_deliveries', [
            'event_id' => $event->eventId()->value,
            'status' => 'Accepted',
        ]);
    }

    public function test_quote_is_not_locked_without_billing_email_then_uses_current_recipient(): void
    {
        $owner = $this->onboardOwner($this, 'quote-recipient@test.local');
        $this->app->make(OutboxProcessor::class)->processPending(20);
        $this->emails->reset();

        $client = $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Client sans routage',
            'profile' => ['email' => 'contact@client.test'],
        ], $this->headers($owner['token']))->assertCreated();

        $quote = $this->postJson("/api/workspaces/{$owner['workspace_id']}/quotes", [
            'client_id' => $client->json('client_id'),
            'lines' => [['description' => 'Conseil', 'quantity' => 1, 'unit_price_cents' => 25000]],
        ], $this->headers($owner['token']))->assertCreated();

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->json('quote_id')}/send",
            ['expected_revision' => $quote->json('version')],
            $this->headers($owner['token']),
        )->assertUnprocessable()
            ->assertJsonPath('messages.0', 'A client billing email is required before sending this quote.');

        $this->assertDatabaseHas('billing.quotes', [
            'id' => $quote->json('quote_id'),
            'status' => 'Draft',
        ]);
        $this->assertDatabaseCount('platform.email_deliveries', 1);

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client->json('client_id')}/billing-profile",
            [
                'billing_profile' => ['billing_email' => 'factures@client.test'],
                'expected_revision' => 1,
            ],
            $this->headers($owner['token']),
        )->assertOk();

        $sent = $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->json('quote_id')}/send",
            ['expected_revision' => $quote->json('version')],
            $this->headers($owner['token']),
        )->assertOk()
            ->assertJsonPath('status', 'Sent')
            ->assertJsonPath('delivery_status', 'Pending')
            ->assertJsonPath('resent', false);

        $deliveryEvent = DB::table('platform.outbox_messages')
            ->where('event_type', 'billing.quote_delivery_requested')
            ->whereRaw("payload->>'quote_id' = ?", [$quote->json('quote_id')])
            ->latest('created_at')
            ->first();
        $this->assertNotNull($deliveryEvent);
        $this->assertStringNotContainsString('factures@client.test', (string) $deliveryEvent->payload);

        $this->app->make(OutboxProcessor::class)->processPending(40);

        $this->assertCount(1, $this->emails->sent);
        $this->assertSame('factures@client.test', $this->emails->sent[0]->recipient);
        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->json('quote_id')}",
            ['Authorization' => 'Bearer '.$owner['token']],
        )->assertOk()->assertJsonPath('email_delivery_status', 'Accepted');

        $this->putJson(
            "/api/workspaces/{$owner['workspace_id']}/clients/{$client->json('client_id')}/billing-profile",
            [
                'billing_profile' => ['billing_email' => 'nouvelle-facturation@client.test'],
                'expected_revision' => 2,
            ],
            $this->headers($owner['token']),
        )->assertOk();

        $this->emails->reset();
        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->json('quote_id')}/send",
            ['expected_revision' => $sent->json('version')],
            $this->headers($owner['token']),
        )->assertOk()
            ->assertJsonPath('resent', true)
            ->assertJsonPath('delivery_status', 'Pending');

        $this->app->make(OutboxProcessor::class)->processPending(40);

        $this->assertCount(1, $this->emails->sent);
        $this->assertSame('nouvelle-facturation@client.test', $this->emails->sent[0]->recipient);
        $this->assertSame(2, DB::table('platform.outbox_messages')
            ->where('event_type', 'billing.quote_delivery_requested')
            ->whereRaw("payload->>'quote_id' = ?", [$quote->json('quote_id')])
            ->count());
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => (string) Str::uuid(),
        ];
    }
}
