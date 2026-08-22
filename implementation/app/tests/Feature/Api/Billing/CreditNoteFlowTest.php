<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Atlas\Modules\Analytics\Application\MetricCalculator;
use Atlas\Modules\Analytics\Domain\MetricKeys;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class CreditNoteFlowTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;

    public function test_owner_creates_issues_and_applies_credit_note_atomically(): void
    {
        $owner = $this->onboardOwner($this, 'credit-note@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);
        $headers = $this->headers($owner['token']);

        $creditNote = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/credit-notes", [
            'lines' => [['description' => 'Correction prestation', 'quantity' => 1, 'unit_price_cents' => 50000]],
            'reason' => 'Prestation annulée',
        ], $headers)->assertCreated()
            ->assertJsonPath('status', 'Draft')
            ->assertJsonPath('total_cents', 50000);

        $creditNoteId = $creditNote->json('credit_note_id');

        $issued = $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$creditNoteId}/issue", [
            'expected_revision' => 1,
        ], $headers)->assertOk()
            ->assertJsonPath('status', 'Issued')
            ->assertJsonPath('credit_note_number', 'CN-000001');

        app(OutboxProcessor::class)->processPending();
        $this->assertDatabaseHas('analytics.source_facts', [
            'workspace_id' => $owner['workspace_id'],
            'aggregate_type' => 'credit_note',
            'aggregate_id' => $creditNoteId,
        ]);

        $net = app(MetricCalculator::class)->calculateMetric(
            $owner['workspace_id'],
            MetricKeys::BILLING_NET_INVOICED_AMOUNT,
            new \DateTimeImmutable('+1 minute'),
        );
        $this->assertSame(0, $net['values_by_currency']['EUR']);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$creditNoteId}/apply", [
            'amount_cents' => 50000,
            'expected_credit_note_revision' => $issued->json('version'),
            'expected_invoice_revision' => 1,
        ], $headers)->assertOk()
            ->assertJsonPath('status', 'Applied')
            ->assertJsonPath('invoice_balance_cents', 0)
            ->assertJsonPath('invoice_settlement_status', 'Paid');

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('balance_cents', 0)
            ->assertJsonPath('paid_at', null)
            ->assertJsonPath('credit_notes.0.status', 'Applied');

        $this->assertDatabaseHas('billing.credit_notes', [
            'id' => $creditNoteId,
            'amount_applied_cents' => 50000,
        ]);
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'billing.invoice_settled')->count());
    }

    public function test_partial_application_requires_remainder_disposition(): void
    {
        $owner = $this->onboardOwner($this, 'credit-note-remainder@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);
        $headers = $this->headers($owner['token']);
        $creditNoteId = $this->createAndIssue($owner['workspace_id'], $invoiceId, $headers, 30000);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$creditNoteId}/apply", [
            'amount_cents' => 20000,
            'expected_credit_note_revision' => 2,
            'expected_invoice_revision' => 1,
        ], $headers)->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Remainder disposition required.');

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$creditNoteId}/apply", [
            'amount_cents' => 20000,
            'remainder_disposition' => 'ClientCredit',
            'expected_credit_note_revision' => 2,
            'expected_invoice_revision' => 1,
        ], $headers)->assertOk()
            ->assertJsonPath('unapplied_amount_cents', 10000)
            ->assertJsonPath('remainder_disposition', 'ClientCredit')
            ->assertJsonPath('invoice_balance_cents', 30000);
    }

    public function test_issued_credit_notes_cannot_exceed_invoice_total(): void
    {
        $owner = $this->onboardOwner($this, 'credit-note-cap@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);
        $headers = $this->headers($owner['token']);
        $this->createAndIssue($owner['workspace_id'], $invoiceId, $headers, 30000);

        $second = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/credit-notes", [
            'lines' => [['description' => 'Deuxième correction', 'quantity' => 1, 'unit_price_cents' => 25000]],
        ], $this->headers($owner['token']))->assertCreated();

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$second->json('credit_note_id')}/issue", [
            'expected_revision' => 1,
        ], $this->headers($owner['token']))->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Credit notes exceed invoice total.');
    }

    public function test_draft_can_be_updated_discarded_and_replayed_idempotently(): void
    {
        $owner = $this->onboardOwner($this, 'credit-note-draft@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);
        $key = (string) Str::uuid();
        $headers = [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => $key,
        ];
        $payload = [
            'lines' => [['description' => 'Correction', 'quantity' => 1, 'unit_price_cents' => 10000]],
        ];

        $first = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/credit-notes", $payload, $headers)
            ->assertCreated()->json();
        $second = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/credit-notes", $payload, $headers)
            ->assertCreated()->json();
        $this->assertEquals($first, $second);

        $id = $first['credit_note_id'];
        $updated = $this->patchJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$id}", [
            'lines' => [['description' => 'Correction ajustée', 'quantity' => 2, 'unit_price_cents' => 7500]],
            'expected_revision' => 1,
        ], $this->headers($owner['token']))->assertOk()
            ->assertJsonPath('total_cents', 15000);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/credit-notes/{$id}/discard", [
            'expected_revision' => $updated->json('version'),
        ], $this->headers($owner['token']))->assertOk()
            ->assertJsonPath('status', 'Discarded');
    }

    public function test_member_without_credit_note_permission_is_forbidden(): void
    {
        $owner = $this->onboardOwner($this, 'credit-note-owner@test.local');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'credit-note-member@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/credit-notes", [
            'lines' => [['description' => 'Intrusion', 'quantity' => 1, 'unit_price_cents' => 1000]],
        ], $this->headers($member['token']))->assertForbidden();
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => (string) Str::uuid(),
        ];
    }

    /** @param array<string, string> $headers */
    private function createAndIssue(string $workspaceId, string $invoiceId, array $headers, int $amount): string
    {
        $created = $this->postJson("/api/workspaces/{$workspaceId}/invoices/{$invoiceId}/credit-notes", [
            'lines' => [['description' => 'Correction', 'quantity' => 1, 'unit_price_cents' => $amount]],
        ], $headers)->assertCreated();
        $id = $created->json('credit_note_id');
        $this->postJson("/api/workspaces/{$workspaceId}/credit-notes/{$id}/issue", [
            'expected_revision' => 1,
        ], $this->headers(substr($headers['Authorization'], 7)))->assertOk();
        return $id;
    }

    private function issuedInvoice(string $workspaceId, int $totalCents): string
    {
        $invoiceId = (string) Str::uuid();
        DB::table('billing.invoices')->insert([
            'id' => $invoiceId,
            'workspace_id' => $workspaceId,
            'client_id' => (string) Str::uuid(),
            'quote_id' => null,
            'status' => 'Issued',
            'settlement_status' => 'Unpaid',
            'invoice_number' => 'INV-CN-TEST-'.substr($invoiceId, 0, 8),
            'lines' => json_encode([['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $totalCents]], JSON_THROW_ON_ERROR),
            'total_cents' => $totalCents,
            'balance_cents' => $totalCents,
            'currency' => 'EUR',
            'client_snapshot' => json_encode(['display_name' => 'Client avoir'], JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
            'is_historical_import' => false,
        ]);
        return $invoiceId;
    }
}
