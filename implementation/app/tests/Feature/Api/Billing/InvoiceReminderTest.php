<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class InvoiceReminderTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;

    public function test_owner_records_manual_reminder_without_changing_pdf_artifact(): void
    {
        $owner = $this->onboardOwner($this, 'invoice-remind@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);
        $headers = $this->headers($owner['token']);

        $first = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/remind", [
            'expected_revision' => 1,
            'delivery' => 'ManualChannel',
            'message' => 'Solde toujours dû',
        ], $headers)->assertOk();

        $this->assertSame(2, $first->json('version'));
        $this->assertSame(1, $first->json('reminder_count'));
        $this->assertNotNull($first->json('last_reminded_at'));
        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'billing.invoice_reminder_requested',
        ]);

        $replay = $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/remind", [
            'expected_revision' => 1,
            'delivery' => 'ManualChannel',
            'message' => 'Solde toujours dû',
        ], $headers)->assertOk()->json();
        $this->assertEquals($first->json(), $replay);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/remind", [
            'expected_revision' => 2,
            'delivery' => 'ManualChannel',
        ], $this->headers($owner['token']))->assertOk()
            ->assertJsonPath('reminder_count', 2)
            ->assertJsonPath('version', 3);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('reminder_count', 2)
            ->assertJsonPath('balance_cents', 50000);
    }

    public function test_paid_and_historical_invoices_cannot_be_reminded(): void
    {
        $owner = $this->onboardOwner($this, 'invoice-remind-blocked@test.local');
        $headers = $this->headers($owner['token']);

        $paidId = $this->issuedInvoice($owner['workspace_id'], 10000, balance: 0);
        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$paidId}/remind", [
            'expected_revision' => 1,
        ], $headers)->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Invoice has no outstanding balance.');

        $historicalId = $this->issuedInvoice($owner['workspace_id'], 10000, historical: true);
        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$historicalId}/remind", [
            'expected_revision' => 1,
        ], $this->headers($owner['token']))->assertUnprocessable()
            ->assertJsonPath('messages.0', 'Historical imports are read-only.');
    }

    public function test_member_without_remind_permission_is_forbidden(): void
    {
        $owner = $this->onboardOwner($this, 'invoice-remind-owner@test.local');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'invoice-remind-member@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000);

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$invoiceId}/remind", [
            'expected_revision' => 1,
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

    private function issuedInvoice(
        string $workspaceId,
        int $totalCents,
        ?int $balance = null,
        bool $historical = false,
    ): string {
        $invoiceId = (string) Str::uuid();
        DB::table('billing.invoices')->insert([
            'id' => $invoiceId,
            'workspace_id' => $workspaceId,
            'client_id' => (string) Str::uuid(),
            'quote_id' => null,
            'status' => 'Issued',
            'settlement_status' => ($balance ?? $totalCents) === 0 ? 'Paid' : 'Unpaid',
            'invoice_number' => 'INV-REM-'.substr($invoiceId, 0, 8),
            'lines' => json_encode([['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $totalCents]], JSON_THROW_ON_ERROR),
            'total_cents' => $totalCents,
            'balance_cents' => $balance ?? $totalCents,
            'currency' => 'EUR',
            'client_snapshot' => json_encode(['display_name' => 'Client relance'], JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
            'is_historical_import' => $historical,
        ]);

        return $invoiceId;
    }
}
