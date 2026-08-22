<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Analytics;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;

final class HistoricalImportAnalyticsRebuildTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;

    public function test_correlated_import_completions_rebuild_analytics_without_operational_events(): void
    {
        $owner = $this->onboardOwner($this, 'historical-rebuild@test.local');
        $headers = $this->headers($owner['token']);

        $clientPreview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', implode("\n", [
                    'external_id,kind,status,display_name,source_created_at',
                    'client-42,Organization,Active,Legacy client,2024-01-10T09:30:00Z',
                ])),
            ],
            $headers,
        )->assertCreated()->json();

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/confirm",
            [
                'preview_id' => $clientPreview['preview_id'],
                'package_hash' => 'sha256:'.$clientPreview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()->assertJsonPath('status', 'Completed');

        $this->assertSame(0, DB::table('analytics.historical_import_rebuilds')->count());

        $billingPreview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                ...$this->billingFiles(),
            ],
            $headers,
        )->assertCreated()->assertJsonPath('valid_for_confirmation', true)->json();

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $billingPreview['preview_id'],
                'package_hash' => 'sha256:'.$billingPreview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()->assertJsonPath('status', 'Completed');

        $rebuild = DB::table('analytics.historical_import_rebuilds')->sole();
        $this->assertSame('Completed', $rebuild->status);
        $this->assertSame(1, (int) $rebuild->quote_fact_count);
        $this->assertSame(1, (int) $rebuild->invoice_fact_count);
        $this->assertSame(1, (int) $rebuild->payment_fact_count);

        $this->assertSame(1, DB::table('analytics.projection_generations')->where('status', 'Active')->where('rebuild_reason', 'HistoricalImport')->count());
        $this->assertSame(1, DB::table('analytics.source_facts')->where('source_event_type', 'analytics.historical_import.quote')->count());
        $this->assertSame(1, DB::table('analytics.source_facts')->where('source_event_type', 'analytics.historical_import.invoice')->count());
        $this->assertSame(1, DB::table('analytics.source_facts')->where('source_event_type', 'analytics.historical_import.payment')->count());
        $this->assertSame(1, DB::table('analytics.snapshots')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'analytics.projection_rebuilt')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'billing.quote_sent')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'billing.invoice_issued')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'billing.payment_recorded')->count());

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $billingPreview['preview_id'],
                'package_hash' => 'sha256:'.$billingPreview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $headers + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted();

        $this->assertSame(1, DB::table('analytics.historical_import_rebuilds')->count());
        $this->assertSame(3, DB::table('analytics.source_facts')->where('source_event_type', 'like', 'analytics.historical_import.%')->count());
        $this->assertSame(1, DB::table('analytics.snapshots')->count());
    }

    /** @return array<string, UploadedFile> */
    private function billingFiles(): array
    {
        return [
            'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', implode("\n", [
                'external_id,client_external_id,original_number,status,created_at,sent_at,responded_at,valid_until,net_amount_cents,tax_amount_cents,gross_amount_cents,currency',
                'quote-42,client-42,Q-OLD-42,Accepted,2025-01-01T00:00:00Z,2025-01-02T00:00:00Z,2025-01-03T00:00:00Z,2025-02-01T00:00:00Z,8000,2000,10000,EUR',
            ])),
            'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', implode("\n", [
                'external_id,client_external_id,original_number,issued_at,due_date,net_amount_cents,tax_amount_cents,gross_amount_cents,currency',
                'invoice-42,client-42,INV-OLD-42,2025-01-04T00:00:00Z,2025-02-04T00:00:00Z,8000,2000,10000,EUR',
            ])),
            'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', implode("\n", [
                'external_id,invoice_external_id,amount_received_cents,amount_applied_cents,currency,received_at,status',
                'payment-42,invoice-42,3000,3000,EUR,2025-01-10T00:00:00Z,Active',
            ])),
        ];
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }
}
