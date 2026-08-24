<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorDataExportTest extends IntegrationTestCase
{
    public function test_two_distinct_operators_request_approve_generate_and_download_a_bounded_export(): void
    {
        $this->enableActions();
        $subject = $this->workspaceOwner('export-subject@example.test');
        $this->artisan('atlas:compliance:request', [
            'workspace-id' => $subject['workspace_id'], 'requester-email' => $subject['email'],
            'type' => 'Access', 'summary-code' => 'rights.export-fixture', '--reason' => 'Recette export assisté',
        ])->assertSuccessful();
        $dataRequestReference = (string) DB::table('operations.data_requests')->value('reference');

        $requester = $this->operator($subject['email'], $subject['user_id'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS, OperatorPermissionCatalog::COMPLIANCE_READ,
            OperatorPermissionCatalog::EXPORTS_REQUEST, OperatorPermissionCatalog::EXPORTS_APPROVE,
        ]);
        $approver = $this->newOperator('export-approver@example.test', [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS, OperatorPermissionCatalog::COMPLIANCE_READ,
            OperatorPermissionCatalog::EXPORTS_APPROVE, OperatorPermissionCatalog::EXPORTS_DOWNLOAD,
        ]);

        $requestReason = 'export.subject-request-qualified';
        $requestPreview = $this->withToken($requester['token'])
            ->postJson('/api/operator/data-requests/'.$dataRequestReference.'/exports/request-preview', ['reason_code' => $requestReason])
            ->assertOk()
            ->assertJsonPath('proposed_status', 'AwaitingApproval')
            ->assertJsonPath('requires_distinct_approver', true)
            ->assertJsonPath('effects.2', 'Ne génère et ne transmet encore aucun fichier');
        $requestKey = (string) Str::uuid();
        $requestPayload = ['reason_code' => $requestReason, 'preview_fingerprint' => (string) $requestPreview->json('preview_fingerprint')];
        $created = $this->withToken($requester['token'])->withHeader('Idempotency-Key', $requestKey)
            ->postJson('/api/operator/data-requests/'.$dataRequestReference.'/exports', $requestPayload)
            ->assertCreated()
            ->assertJsonPath('status', 'AwaitingApproval')
            ->assertJsonPath('replayed', false);
        $exportReference = (string) $created->json('reference');
        $this->withToken($requester['token'])->withHeader('Idempotency-Key', $requestKey)
            ->postJson('/api/operator/data-requests/'.$dataRequestReference.'/exports', $requestPayload)
            ->assertCreated()
            ->assertJsonPath('replayed', true);

        $approvalReason = 'export.scope-and-owner-reviewed';
        $this->withToken($requester['token'])
            ->postJson('/api/operator/data-exports/'.$exportReference.'/approval-preview', ['reason_code' => $approvalReason])
            ->assertForbidden()
            ->assertJsonPath('error', 'DataExportDistinctApproverRequired');

        $approvalPreview = $this->withToken($approver['token'])
            ->postJson('/api/operator/data-exports/'.$exportReference.'/approval-preview', ['reason_code' => $approvalReason])
            ->assertOk()
            ->assertJsonPath('proposed_status', 'Generating');
        $approvalKey = (string) Str::uuid();
        $this->withToken($approver['token'])->withHeader('Idempotency-Key', $approvalKey)
            ->patchJson('/api/operator/data-exports/'.$exportReference.'/approval', [
                'reason_code' => $approvalReason,
                'preview_fingerprint' => (string) $approvalPreview->json('preview_fingerprint'),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'Generating');
        $this->assertDatabaseHas('platform.outbox_messages', ['event_type' => 'operations.data_export.generation_requested']);

        self::assertGreaterThanOrEqual(1, app(OutboxProcessor::class)->processPending(20));
        $this->assertDatabaseHas('operations.data_exports', ['reference' => $exportReference, 'status' => 'Ready']);
        self::assertSame(1, DB::table('operations.data_export_artifacts')->count());
        self::assertStringNotContainsString($subject['email'], (string) DB::table('operations.data_export_artifacts')->value('ciphertext'));
        $list = $this->withToken($approver['token'])->getJson('/api/operator/overview/data-requests')
            ->assertOk()
            ->assertJsonPath('items.0.export.reference', $exportReference)
            ->assertJsonPath('items.0.export.status', 'Ready')
            ->assertJsonMissingPath('items.0.export.ciphertext');

        $downloadKey = (string) Str::uuid();
        $download = $this->withToken($approver['token'])->withHeader('Idempotency-Key', $downloadKey)
            ->postJson('/api/operator/data-exports/'.$exportReference.'/download', ['reason_code' => 'export.secure-delivery'])
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Atlas-Idempotent-Replay', 'false');
        $payload = json_decode($download->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('atlas.workspace-data-export', $payload['schema']);
        self::assertSame($subject['workspace_id'], $payload['workspace']['id']);
        self::assertSame($subject['email'], $payload['identity']['members'][0]['email']);
        self::assertStringNotContainsString('password_hash', $download->getContent());
        self::assertStringNotContainsString('provider_subscription_reference', $download->getContent());

        $this->withToken($approver['token'])->withHeader('Idempotency-Key', $downloadKey)
            ->postJson('/api/operator/data-exports/'.$exportReference.'/download', ['reason_code' => 'export.secure-delivery'])
            ->assertOk()
            ->assertHeader('X-Atlas-Idempotent-Replay', 'true');
        self::assertSame(1, DB::table('operations.data_export_events')->where('event_type', 'Downloaded')->count());
        $this->assertDatabaseHas('operations.data_requests', ['reference' => $dataRequestReference, 'status' => 'Delivered']);
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.data-export.downloaded', 'permission' => OperatorPermissionCatalog::EXPORTS_DOWNLOAD, 'result' => 'Succeeded',
        ]);
        self::assertNotNull($list->json('items.0.export.byte_size'));

        DB::table('operations.data_exports')->where('reference', $exportReference)->update(['expires_at' => now('UTC')->subMinute()]);
        $this->withToken($approver['token'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/operator/data-exports/'.$exportReference.'/download', ['reason_code' => 'export.secure-delivery'])
            ->assertGone()
            ->assertJsonPath('error', 'DataExportExpired');
        $this->assertDatabaseHas('operations.data_exports', ['reference' => $exportReference, 'status' => 'Expired']);
        self::assertSame(0, DB::table('operations.data_export_artifacts')->count());
    }

    public function test_action_flags_permission_and_step_up_are_enforced_independently(): void
    {
        $subject = $this->workspaceOwner('export-gates-subject@example.test');
        $this->artisan('atlas:compliance:request', [
            'workspace-id' => $subject['workspace_id'], 'requester-email' => $subject['email'],
            'type' => 'Portability', 'summary-code' => 'rights.portability-fixture', '--reason' => 'Recette gates export',
        ])->assertSuccessful();
        $reference = (string) DB::table('operations.data_requests')->value('reference');
        $operator = $this->operator($subject['email'], $subject['user_id'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS, OperatorPermissionCatalog::EXPORTS_REQUEST,
        ]);

        $this->withToken($operator['token'])
            ->postJson('/api/operator/data-requests/'.$reference.'/exports/request-preview', ['reason_code' => 'export.subject-request-qualified'])
            ->assertForbidden()->assertJsonPath('error', 'OperatorActionsDisabled');

        $this->enableActions();
        $this->grant($subject['email'], [OperatorPermissionCatalog::BACKOFFICE_ACCESS]);
        $this->withToken($operator['token'])
            ->postJson('/api/operator/data-requests/'.$reference.'/exports/request-preview', ['reason_code' => 'export.subject-request-qualified'])
            ->assertForbidden()->assertJsonPath('error', 'OperatorForbidden');

        $this->grant($subject['email'], [OperatorPermissionCatalog::BACKOFFICE_ACCESS, OperatorPermissionCatalog::EXPORTS_REQUEST]);
        DB::table('operations.operator_sessions')->where('id', $operator['session_id'])->update(['step_up_at' => null]);
        $this->withToken($operator['token'])
            ->postJson('/api/operator/data-requests/'.$reference.'/exports/request-preview', ['reason_code' => 'export.subject-request-qualified'])
            ->assertForbidden()->assertJsonPath('error', 'OperatorStepUpRequired');
    }

    /** @return array{email:string,user_id:string,workspace_id:string} */
    private function workspaceOwner(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email, 'display_name' => 'Export Subject', 'password' => 'password123', 'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', ['user_id' => $userId, 'token' => (string) $register->json('verification_token')])->assertOk();
        $login = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();
        $workspace = $this->withToken((string) $login->json('token'))->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/workspaces/first', ['name' => 'Export Fixture'])->assertCreated();

        return ['email' => $email, 'user_id' => $userId, 'workspace_id' => (string) $workspace->json('workspace_id')];
    }

    /** @param list<string> $permissions @return array{token:string,session_id:string} */
    private function newOperator(string $email, array $permissions): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email, 'display_name' => 'Export Approver', 'password' => 'password123', 'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', ['user_id' => $userId, 'token' => (string) $register->json('verification_token')])->assertOk();

        return $this->operator($email, $userId, $permissions);
    }

    /** @param list<string> $permissions @return array{token:string,session_id:string} */
    private function operator(string $email, string $userId, array $permissions): array
    {
        $this->grant($email, $permissions);
        $enrollment = app(EnrollOperatorMfaHandler::class)->handle($userId, $email, 'Atlas Back-office Test', 'Recette export assisté');
        $login = $this->postJson('/api/operator/auth/login', [
            'email' => $email, 'password' => 'password123', 'mfa_code' => $enrollment['recovery_codes'][0],
        ])->assertOk();

        return ['token' => (string) $login->json('token'), 'session_id' => (string) $login->json('session_id')];
    }

    /** @param list<string> $permissions */
    private function grant(string $email, array $permissions): void
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email, '--permissions' => implode(',', $permissions), '--reason' => 'Recette permissions export assisté',
        ])->assertSuccessful();
    }

    private function enableActions(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
    }
}
