<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Crm;

use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AddsWorkspaceMember;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\ElevatesSession;

final class ClientHistoryImportPreviewTest extends IntegrationTestCase
{
    use AddsWorkspaceMember;
    use AuthenticatesWorkspaceOwner;
    use ElevatesSession;

    public function test_owner_previews_a_bounded_canonical_client_package_without_business_mutation(): void
    {
        $owner = $this->onboardOwner($this);
        $csv = implode("\n", [
            'external_id,kind,status,display_name,legal_name,email,phone,website,source_created_at',
            'client-001,Organization,Active,Atelier Noroît,Atelier Noroît SAS,bonjour@noroit.test,+33102030405,https://noroit.test,2024-01-10T09:30:00Z',
            'client-002,Individual,Archived,Lina Moreau,,lina@example.test,,,2023-06-01T14:00:00+02:00',
        ]);

        $response = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $csv),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('schema_version', '1.0')
            ->assertJsonPath('source_system', 'LegacyCRM')
            ->assertJsonPath('row_count', 2)
            ->assertJsonPath('valid_row_count', 2)
            ->assertJsonPath('validation_error_count', 0)
            ->assertJsonPath('duplicate_candidate_count', 0)
            ->assertJsonPath('valid_for_confirmation', true)
            ->assertJsonPath('records.0.profile.display_name', 'Atelier Noroît')
            ->assertJsonPath('records.0.validation_status', 'Valid')
            ->assertJsonPath('records.1.status', 'Archived')
            ->assertJsonPath('package_hash', fn ($value): bool => is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1)
            ->assertJsonPath('expires_at', fn ($value): bool => is_string($value) && $value !== '');

        $preview = DB::table('crm.client_history_import_previews')->sole();
        $this->assertSame($response->json('preview_id'), $preview->id);
        $this->assertSame($response->json('package_hash'), $preview->package_hash);
        $this->assertSame($owner['user_id'], $preview->created_by);
        $this->assertFalse(property_exists($preview, 'raw_file'));
        $this->assertSame(0, DB::table('crm.clients')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_created')->count());
    }

    public function test_owner_can_confirm_a_valid_preview_and_outbox_materializes_clients(): void
    {
        $owner = $this->onboardOwner($this, 'confirm@crm.test');
        $csv = implode("\n", [
            'external_id,kind,status,display_name,legal_name,email,phone,website,source_created_at',
            'client-001,Organization,Active,Atelier Noroît,Atelier Noroît SAS,bonjour@noroit.test,+33102030405,https://noroit.test,2024-01-10T09:30:00Z',
        ]);

        $preview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $csv),
            ],
            $this->headers($owner['token']),
        )->assertCreated()->json();

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertForbidden()
            ->assertJsonPath('error', 'StepUpRequired');

        $this->elevateSession($this, $owner);

        $idempotencyKey = (string) Str::uuid();
        $response = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => $idempotencyKey],
        )->assertAccepted()
            ->assertJsonPath('import_run_id', fn ($value): bool => is_string($value) && Str::isUuid($value))
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('client_count', 1)
            ->assertJsonPath('processed_count', 1)
            ->json();

        $this->get(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/{$response['import_run_id']}",
            $this->headers($owner['token']),
        )->assertOk()
            ->assertJsonPath('status', 'Completed')
            ->assertJsonPath('processed_count', 1);

        $this->assertSame(1, DB::table('crm.clients')->count());
        $client = DB::table('crm.clients')->first();
        $this->assertSame('Atelier Noroît', $client->display_name);
        $this->assertSame('LegacyCRM', $client->source_system);
        $this->assertSame('client-001', $client->external_id);
        $this->assertSame(0, (int) $client->billing_profile_version);
        $this->assertNotNull($client->canonical_record_hash);

        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_history_import_requested')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_history_import_completed')->count());
        $this->assertSame(0, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_created')->count());

        $replay = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/confirm",
            [
                'preview_id' => $preview['preview_id'],
                'package_hash' => 'sha256:'.$preview['package_hash'],
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()
            ->assertJsonPath('import_run_id', $response['import_run_id']);

        $this->assertSame(1, DB::table('crm.clients')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_history_import_requested')->count());
    }

    public function test_conflicting_historical_identity_is_blocked_before_confirmation(): void
    {
        $owner = $this->onboardOwner($this, 'conflict-import@crm.test');
        $firstCsv = implode("\n", [
            'external_id,kind,status,display_name,source_created_at',
            'client-001,Organization,Active,Atelier Noroît,2024-01-10T09:30:00Z',
        ]);

        $firstPreview = $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $firstCsv),
            ],
            $this->headers($owner['token']),
        )->assertCreated()->json();

        $this->elevateSession($this, $owner);
        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/confirm",
            [
                'preview_id' => $firstPreview['preview_id'],
                'package_hash' => $firstPreview['package_hash'],
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
            ],
            $this->headers($owner['token']) + ['Idempotency-Key' => (string) Str::uuid()],
        )->assertAccepted()->assertJsonPath('status', 'Completed');

        $alteredCsv = implode("\n", [
            'external_id,kind,status,display_name,source_created_at',
            'client-001,Organization,Active,Atelier Noroît modifié,2024-01-10T09:30:00Z',
        ]);

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2025-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $alteredCsv),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonPath('duplicate_candidates.0.kind', 'ConflictingHistoricalIdentity');
    }

    public function test_preview_exposes_validation_errors_and_probable_duplicates_before_confirmation(): void
    {
        $owner = $this->onboardOwner($this, 'duplicates@crm.test');
        $headers = ['Authorization' => 'Bearer '.$owner['token']];

        $this->postJson("/api/workspaces/{$owner['workspace_id']}/clients", [
            'kind' => 'Organization',
            'display_name' => 'Client déjà présent',
        ], $headers + ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $csv = implode("\n", [
            'external_id;kind;status;display_name;email;source_created_at',
            'duplicate-001;Company;Active;Client déjà présent;adresse-invalide;2026-01-02T10:00:00Z',
            'duplicate-001;Individual;Active;Nouvelle personne;;2025-01-01T10:00:00Z',
        ]);

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'Legacy_CRM',
                'source_exported_at' => '2026-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $csv),
            ],
            $this->headers($owner['token']),
        )->assertCreated()
            ->assertJsonPath('row_count', 2)
            ->assertJsonPath('valid_row_count', 0)
            ->assertJsonPath('valid_for_confirmation', false)
            ->assertJsonPath('duplicate_candidate_count', 1)
            ->assertJsonPath('duplicate_candidates.0.kind', 'ExistingClient')
            ->assertJsonPath('duplicate_candidates.0.matched_display_name', 'Client déjà présent')
            ->assertJsonCount(2, 'records')
            ->assertJsonPath('records.0.validation_status', 'Invalid')
            ->assertJsonPath('records.1.validation_status', 'Invalid')
            ->assertJsonFragment(['code' => 'invalid_kind'])
            ->assertJsonFragment(['code' => 'invalid_email'])
            ->assertJsonFragment(['code' => 'source_date_after_export'])
            ->assertJsonFragment(['code' => 'duplicate_external_id']);

        $this->assertSame(1, DB::table('crm.clients')->count());
        $this->assertSame(1, DB::table('platform.outbox_messages')->where('event_type', 'crm.client_created')->count());
    }

    public function test_member_without_critical_permission_cannot_preview_an_import(): void
    {
        $owner = $this->onboardOwner($this, 'import-owner@crm.test');
        $member = $this->addMemberToWorkspace($this, $owner['workspace_id'], 'import-member@crm.test');
        $csv = implode("\n", [
            'external_id,kind,status,display_name,source_created_at',
            'client-001,Organization,Active,Client interdit,2025-01-01T10:00:00Z',
        ]);

        $this->post(
            "/api/workspaces/{$owner['workspace_id']}/client-history-imports/preview",
            [
                'source_system' => 'LegacyCRM',
                'source_exported_at' => '2026-01-01T12:00:00Z',
                'file' => UploadedFile::fake()->createWithContent('clients.csv', $csv),
            ],
            $this->headers($member['token']),
        )->assertForbidden()
            ->assertJsonPath('messages.0', 'Unauthorized.');

        $this->assertSame(0, DB::table('crm.client_history_import_previews')->count());
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }
}
