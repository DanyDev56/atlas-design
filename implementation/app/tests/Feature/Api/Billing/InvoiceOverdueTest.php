<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class InvoiceOverdueTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_scheduler_materializes_overdue_once_and_skips_ineligible_invoices(): void
    {
        $owner = $this->onboardOwner($this, 'invoice-overdue@test.local');
        $pastDueId = $this->issuedInvoice($owner['workspace_id'], 50000, dueDaysAgo: 2);
        $futureId = $this->issuedInvoice($owner['workspace_id'], 40000, dueDaysAgo: -10);
        $paidId = $this->issuedInvoice($owner['workspace_id'], 30000, dueDaysAgo: 5, balance: 0);
        $historicalId = $this->issuedInvoice($owner['workspace_id'], 20000, dueDaysAgo: 5, historical: true);

        $this->artisan('atlas:billing:mark-overdue')
            ->expectsOutputToContain('marked 1 overdue')
            ->assertSuccessful();

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_type' => 'billing.invoice_overdue',
        ]);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$pastDueId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()
            ->assertJsonPath('overdue', true)
            ->assertJsonPath('settlement_status', 'Unpaid')
            ->assertJsonPath('version', 2);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$futureId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()->assertJsonPath('overdue', false);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$paidId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()->assertJsonPath('overdue', false);

        $this->getJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$historicalId}", [
            'Authorization' => 'Bearer '.$owner['token'],
        ])->assertOk()->assertJsonPath('overdue', false);

        $this->artisan('atlas:billing:mark-overdue')
            ->expectsOutputToContain('marked 0 overdue')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('platform.outbox_messages')
            ->where('event_type', 'billing.invoice_overdue')
            ->count());

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/invoices/{$pastDueId}/mark-overdue", [], [
            'Authorization' => 'Bearer '.$owner['token'],
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_not_yet_due_invoice_is_rejected_by_the_command_handler(): void
    {
        $owner = $this->onboardOwner($this, 'invoice-overdue-future@test.local');
        $invoiceId = $this->issuedInvoice($owner['workspace_id'], 50000, dueDaysAgo: -5);

        try {
            app(\Atlas\Modules\Billing\Application\MarkInvoiceOverdueHandler::class)->handle(
                workspaceId: $owner['workspace_id'],
                invoiceId: $invoiceId,
                clock: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
                expectedRevision: 1,
            );
            $this->fail('Expected the invoice to stay current.');
        } catch (\DomainException $exception) {
            $this->assertSame('Invoice is not overdue.', $exception->getMessage());
        }
    }

    private function issuedInvoice(
        string $workspaceId,
        int $totalCents,
        int $dueDaysAgo,
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
            'invoice_number' => 'INV-OVD-'.substr($invoiceId, 0, 8),
            'lines' => json_encode([['description' => 'Service', 'quantity' => 1, 'unit_price_cents' => $totalCents]], JSON_THROW_ON_ERROR),
            'total_cents' => $totalCents,
            'balance_cents' => $balance ?? $totalCents,
            'currency' => 'EUR',
            'client_snapshot' => json_encode(['display_name' => 'Client retard'], JSON_THROW_ON_ERROR),
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'issued_at' => now()->subDays(max(1, $dueDaysAgo + 30)),
            'due_date' => now()->subDays($dueDaysAgo),
            'is_historical_import' => $historical,
        ]);

        return $invoiceId;
    }
}
