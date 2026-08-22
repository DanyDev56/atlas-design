<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Atlas\Modules\Billing\Application\ExecuteHistoricalBillingHistoryImportHandler;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresCreditNoteRepository;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresInvoiceRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\ElevatesSession;

final class BillingHistoryImportTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;
    use ElevatesSession;

    public function test_owner_previews_a_package_without_business_mutation(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history-preview@test.local');
        $this->insertHistoricalClient($owner['workspace_id']);

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                ...$this->validFiles(),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', true)
            ->assertJsonPath('quote_count', 1)
            ->assertJsonPath('invoice_count', 1)
            ->assertJsonPath('payment_count', 1)
            ->assertJsonPath('credit_note_count', 1)
            ->assertJsonPath('quotes.0.client_display_name', 'Legacy client')
            ->assertJsonPath('package_hash', fn ($value): bool => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1);

        $this->assertSame(0, DB::table('billing.quotes')->count());
        $this->assertSame(0, DB::table('billing.invoices')->count());
        $this->assertSame(0, DB::table('billing.payments')->count());
        $this->assertSame(0, DB::table('billing.credit_notes')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'billing.quote_sent')->count());
    }

    public function test_owner_imports_a_historical_package_without_operational_side_effects(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history@test.local');
        $this->insertHistoricalClient($owner['workspace_id']);
        $files = $this->validFiles();

        $preview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                ...$files,
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', true)
            ->json();

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertForbidden()
            ->assertJsonPath('error', 'StepUpRequired');

        $this->elevateSession($this, $owner);
        $idempotencyKey = (string) Str::uuid();
        $response = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => $idempotencyKey],
        )->assertAccepted()
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('checkpoint', 'Completed')
            ->assertJsonPath('processed_quotes', 1)
            ->assertJsonPath('processed_invoices', 1)
            ->assertJsonPath('processed_payments', 1)
            ->assertJsonPath('processed_credit_notes', 1)
            ->json();

        $quote = DB::table('billing.quotes')->sole();
        $invoice = DB::table('billing.invoices')->sole();
        $payment = DB::table('billing.payments')->sole();
        $creditNote = DB::table('billing.credit_notes')->sole();
        $this->assertTrue((bool) $quote->is_historical_import);
        $this->assertSame('Q-OLD-42', $quote->original_number);
        $this->assertTrue((bool) $invoice->is_historical_import);
        $this->assertNull($invoice->invoice_number);
        $this->assertSame('INV-OLD-42', $invoice->original_number);
        $this->assertSame(0, (int) $invoice->balance_cents);
        $this->assertSame('Paid', $invoice->settlement_status);
        $this->assertSame('2025-01-10 00:00:00+00:00', (new \DateTimeImmutable((string) $invoice->paid_at))->format('Y-m-d H:i:sP'));
        $this->assertSame(3000, (int) $payment->amount_applied_cents);
        $this->assertTrue((bool) $creditNote->is_historical_import);
        $this->assertNull($creditNote->credit_note_number);
        $this->assertSame('CN-OLD-42', $creditNote->original_number);
        $this->assertSame(5600, (int) $creditNote->net_amount_cents);
        $this->assertSame(1400, (int) $creditNote->tax_amount_cents);
        $this->assertSame(7000, (int) $creditNote->gross_amount_cents);
        $this->assertSame(7000, (int) $creditNote->amount_applied_cents);
        $this->assertSame(0, DB::table('billing.public_document_proofs')->count());

        foreach (['billing.quote_sent', 'billing.quote_accepted', 'billing.invoice_issued', 'billing.payment_recorded', 'billing.credit_note_issued', 'billing.credit_note_applied_to_invoice'] as $eventType) {
            $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', $eventType)->count());
        }
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'billing.history_import_requested')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'billing.history_import_completed')->count());
        $this->assertSame(0, DB::table('analytics.historical_import_rebuilds')->count());

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->id}",
            $this->headers($owner['token']),
        )->assertOk()
            ->assertJsonPath('original_number', 'Q-OLD-42')
            ->assertJsonPath('is_historical_import', true);

        $this->getJson(
            "/api/workspaces/{$owner['workspace_id']}/invoices/{$invoice->id}",
            $this->headers($owner['token']),
        )->assertOk()
            ->assertJsonPath('credit_notes.0.original_number', 'CN-OLD-42')
            ->assertJsonPath('credit_notes.0.credit_note_number', null)
            ->assertJsonPath('credit_notes.0.is_historical_import', true);

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/quotes/{$quote->id}/send",
            ['expected_revision' => 1],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertUnprocessable()->assertJsonPath('messages.0', 'Historical imports are read-only.');

        $this->postJson(
            "/api/workspaces/{$owner['workspace_id']}/invoices/{$invoice->id}/payments",
            ['amount_cents' => 100],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertUnprocessable()->assertJsonPath('messages.0', 'Historical imports are read-only.');

        $this->get(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/{$response['import_run_id']}",
            $this->headers($owner['token']),
        )->assertOk()->assertJsonPath('status', 'Completed');

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()
            ->assertJsonPath('import_run_id', $response['import_run_id']);

        $this->assertSame(1, DB::table('billing.quotes')->count());
        $this->assertSame(1, DB::table('billing.invoices')->count());
        $this->assertSame(1, DB::table('billing.payments')->count());
        $this->assertSame(1, DB::table('billing.credit_notes')->count());
        $this->assertSame('INV-000001', app(PostgresInvoiceRepository::class)->nextInvoiceNumber($owner['workspace_id']));
        $this->assertSame('CN-000001', app(PostgresCreditNoteRepository::class)->nextNumber($owner['workspace_id']));

        app(ExecuteHistoricalBillingHistoryImportHandler::class)->handle(
            $owner['workspace_id'],
            $response['import_run_id'],
        );
        $this->assertSame(1, DB::table('billing.quotes')->count());
        $this->assertSame(1, DB::table('billing.credit_notes')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'billing.history_import_completed')->count());
    }

    public function test_invalid_credit_note_remainders_dates_and_overapplication_block_confirmation(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history-credit-note-invalid@test.local');
        $this->insertHistoricalClient($owner['workspace_id']);

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', $this->quoteHeader()),
                'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', implode("\n", [
                    $this->invoiceHeader(),
                    'invoice-42,client-42,INV-OLD-42,2025-01-04T00:00:00Z,2025-02-04T00:00:00Z,8000,2000,10000,EUR',
                ])),
                'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', $this->paymentHeader()),
                'credit_notes_file' => UploadedFile::fake()->createWithContent('credit-notes.csv', implode("\n", [
                    $this->creditNoteHeader(),
                    'credit-42,invoice-42,CN-OLD-42,2025-01-06T00:00:00Z,2025-01-05T00:00:00Z,9600,2400,12000,11000,,EUR,Avoir incohérent',
                ])),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonFragment(['code' => 'invalid_remainder'])
            ->assertJsonFragment(['code' => 'invalid_date_order'])
            ->assertJsonFragment(['code' => 'overpayment']);

        $this->assertSame(0, DB::table('billing.credit_notes')->count());
    }

    public function test_headers_only_files_are_allowed_but_an_empty_package_is_not(): void
    {
        $owner = $this->onboardOwner($this, 'empty-billing-history@test.local');

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', $this->quoteHeader()),
                'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', $this->invoiceHeader()),
                'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', $this->paymentHeader()),
                'credit_notes_file' => UploadedFile::fake()->createWithContent('credit-notes.csv', $this->creditNoteHeader()),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonFragment(['code' => 'empty_package']);
    }

    public function test_unresolved_client_and_arithmetic_errors_block_confirmation(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history-invalid@test.local');

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', implode("\n", [
                    $this->quoteHeader(),
                    'quote-42,missing-client,Q-OLD-42,Accepted,2025-01-01T00:00:00Z,2025-01-02T00:00:00Z,2025-01-03T00:00:00Z,2025-02-01T00:00:00Z,8000,2000,9999,EUR',
                ])),
                'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', implode("\n", [
                    $this->invoiceHeader(),
                    'invoice-42,missing-client,INV-OLD-42,2025-01-04T00:00:00Z,2025-02-04T00:00:00Z,8000,2000,10000,EUR',
                ])),
                'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', implode("\n", [
                    $this->paymentHeader(),
                    'payment-42,invoice-missing,3000,3000,EUR,2025-01-10T00:00:00Z,Active',
                ])),
                'credit_notes_file' => UploadedFile::fake()->createWithContent('credit-notes.csv', $this->creditNoteHeader()),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonFragment(['code' => 'unresolved_client'])
            ->assertJsonFragment(['code' => 'calculation_conflict'])
            ->assertJsonFragment(['code' => 'unresolved_invoice']);

        $this->assertSame(0, DB::table('billing.quotes')->count());
    }

    public function test_conflicting_historical_identity_is_blocked_before_confirmation(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history-conflict@test.local');
        $this->insertHistoricalClient($owner['workspace_id']);

        $firstPreview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                ...$this->validFiles(),
            ],
            $this->headers($owner['token']),
        )->assertCreated()->json();

        $this->elevateSession($this, $owner);
        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/confirm",
            [
                'preview_id' => $firstPreview['preview_id'],
                'package_hash' => $firstPreview['package_hash'],
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()->assertJsonPath('status', 'Completed');

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', implode("\n", [
                    $this->quoteHeader(),
                    'quote-42,client-42,Q-OLD-99,Accepted,2025-01-01T00:00:00Z,2025-01-02T00:00:00Z,2025-01-03T00:00:00Z,2025-02-01T00:00:00Z,8000,2000,10000,EUR',
                ])),
                'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', $this->invoiceHeader()),
                'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', $this->paymentHeader()),
                'credit_notes_file' => UploadedFile::fake()->createWithContent('credit-notes.csv', $this->creditNoteHeader()),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonFragment(['code' => 'identity_conflict']);
    }

    public function test_member_without_permission_cannot_preview_an_import(): void
    {
        $owner = $this->onboardOwner($this, 'billing-history-owner@test.local');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'billing-history-member@test.local');

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/billing-history-imports/preview",
            [
                'source_system' => 'LegacySuite',
                'source_exported_at' => '2025-03-01T00:00:00Z',
                ...$this->validFiles(),
            ],
            $this->headers($member['token']),
        )->assertForbidden()
            ->assertJsonPath('messages.0', 'Unauthorized.');

        $this->assertSame(0, DB::table('billing.history_import_previews')->count());
    }

    /** @return array<string, UploadedFile> */
    private function validFiles(): array
    {
        return [
            'quotes_file' => UploadedFile::fake()->createWithContent('quotes.csv', implode("\n", [
                $this->quoteHeader(),
                'quote-42,client-42,Q-OLD-42,Accepted,2025-01-01T00:00:00Z,2025-01-02T00:00:00Z,2025-01-03T00:00:00Z,2025-02-01T00:00:00Z,8000,2000,10000,EUR',
            ])),
            'invoices_file' => UploadedFile::fake()->createWithContent('invoices.csv', implode("\n", [
                $this->invoiceHeader(),
                'invoice-42,client-42,INV-OLD-42,2025-01-04T00:00:00Z,2025-02-04T00:00:00Z,8000,2000,10000,EUR',
            ])),
            'payments_file' => UploadedFile::fake()->createWithContent('payments.csv', implode("\n", [
                $this->paymentHeader(),
                'payment-42,invoice-42,3000,3000,EUR,2025-01-10T00:00:00Z,Active',
            ])),
            'credit_notes_file' => UploadedFile::fake()->createWithContent('credit-notes.csv', implode("\n", [
                $this->creditNoteHeader(),
                'credit-42,invoice-42,CN-OLD-42,2025-01-06T00:00:00Z,2025-01-08T00:00:00Z,5600,1400,7000,7000,,EUR,Avoir historique',
            ])),
        ];
    }

    private function insertHistoricalClient(string $workspaceId): void
    {
        DB::table('crm.clients')->insert([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'kind' => 'Organization',
            'status' => 'Active',
            'display_name' => 'Legacy client',
            'profile' => json_encode(['display_name' => 'Legacy client'], JSON_THROW_ON_ERROR),
            'billing_profile' => json_encode([], JSON_THROW_ON_ERROR),
            'profile_version' => 1,
            'billing_profile_version' => 0,
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'source_system' => 'LegacySuite',
            'external_id' => 'client-42',
            'canonical_record_hash' => hash('sha256', 'client-42'),
        ]);
    }

    private function quoteHeader(): string
    {
        return 'external_id,client_external_id,original_number,status,created_at,sent_at,responded_at,valid_until,net_amount_cents,tax_amount_cents,gross_amount_cents,currency';
    }

    private function invoiceHeader(): string
    {
        return 'external_id,client_external_id,original_number,issued_at,due_date,net_amount_cents,tax_amount_cents,gross_amount_cents,currency';
    }

    private function paymentHeader(): string
    {
        return 'external_id,invoice_external_id,amount_received_cents,amount_applied_cents,currency,received_at,status';
    }

    private function creditNoteHeader(): string
    {
        return 'external_id,invoice_external_id,original_number,issued_at,applied_at,net_amount_cents,tax_amount_cents,gross_amount_cents,amount_applied_cents,remainder_disposition,currency,reason';
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token];
    }
}
