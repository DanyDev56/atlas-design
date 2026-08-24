<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Application\ManageSupportCaseHandler;
use Atlas\Modules\Operations\Application\SupportCaseActionException;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class SupportCaseManagementTest extends IntegrationTestCase
{
    public function test_manager_previews_confirms_and_replays_a_bounded_support_action(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $context = $this->context('support-manager@example.test');
        $proposal = [
            'expected_revision' => 1,
            'status' => 'Acknowledged',
            'assignment' => 'Self',
            'reason_code' => 'case.triage',
        ];
        $preview = $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $proposal)
            ->assertOk()
            ->assertJsonPath('current.status', 'Open')
            ->assertJsonPath('proposed.status', 'Acknowledged')
            ->assertJsonPath('effects.1', 'Aucun email envoyé');
        $key = (string) Str::uuid();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];

        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/support/'.$context['reference'], $payload)
            ->assertOk()
            ->assertJsonPath('status', 'Acknowledged')
            ->assertJsonPath('assignment', 'Self')
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', false);
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/support/'.$context['reference'], $payload)
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', true);

        self::assertSame(2, DB::table('operations.support_cases')->where('reference', $context['reference'])->value('revision'));
        self::assertSame($context['user_id'], DB::table('operations.support_cases')->where('reference', $context['reference'])->value('assigned_operator_user_id'));
        self::assertSame(3, DB::table('operations.support_case_events')->where('support_case_id', $context['case_id'])->count());
        self::assertSame(1, DB::table('operations.operator_action_idempotency')->count());
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.support-case.manage-attempted', 'result' => 'Attempted']);
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.support-case.managed', 'result' => 'Succeeded']);
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.support-case.manage-replayed', 'result' => 'Replayed']);
    }

    public function test_actions_feature_permission_and_step_up_are_independent_gates(): void
    {
        $context = $this->context('support-gates@example.test');
        $proposal = ['expected_revision' => 1, 'status' => 'Acknowledged', 'assignment' => 'Keep', 'reason_code' => 'case.triage'];

        $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorActionsDisabled');

        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $this->grant($context['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::SUPPORT_READ,
        ]);
        $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');

        $this->grant($context['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::SUPPORT_READ,
            OperatorPermissionCatalog::SUPPORT_MANAGE,
        ]);
        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update(['step_up_at' => null]);
        $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorStepUpRequired');
    }

    public function test_stale_preview_idempotency_conflict_and_concurrent_revocation_are_rejected(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $context = $this->context('support-conflicts@example.test');
        $proposal = ['expected_revision' => 1, 'status' => 'Acknowledged', 'assignment' => 'Keep', 'reason_code' => 'case.triage'];
        $preview = $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $proposal)
            ->assertOk();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];
        $key = (string) Str::uuid();
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/support/'.$context['reference'], $payload)
            ->assertOk();

        $this->withToken($context['token'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson('/api/operator/support/'.$context['reference'], $payload)
            ->assertConflict()
            ->assertJsonPath('error', 'SupportCaseRevisionConflict');

        $nextProposal = ['expected_revision' => 2, 'status' => 'InProgress', 'assignment' => 'Keep', 'reason_code' => 'investigation.started'];
        $nextPreview = $this->withToken($context['token'])
            ->postJson('/api/operator/support/'.$context['reference'].'/preview', $nextProposal)
            ->assertOk();
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/support/'.$context['reference'], [
                ...$nextProposal,
                'preview_fingerprint' => (string) $nextPreview->json('preview_fingerprint'),
            ])
            ->assertConflict()
            ->assertJsonPath('error', 'OperatorIdempotencyConflict');

        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update([
            'status' => 'Revoked',
            'revoked_at' => now('UTC'),
        ]);
        try {
            app(ManageSupportCaseHandler::class)->apply(
                $context['reference'],
                $context['user_id'],
                $context['session_id'],
                2,
                'InProgress',
                'Keep',
                'investigation.started',
                (string) $nextPreview->json('preview_fingerprint'),
                (string) Str::uuid(),
                null,
            );
            self::fail('A revoked session must not confirm an action.');
        } catch (SupportCaseActionException $exception) {
            self::assertSame('OperatorAuthorityChanged', $exception->errorCode);
        }
        self::assertSame(2, DB::table('operations.support_cases')->where('reference', $context['reference'])->value('revision'));
    }

    /** @return array{email: string, user_id: string, token: string, session_id: string, reference: string, case_id: string} */
    private function context(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Support Manager',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', [
            'user_id' => $userId,
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();
        $login = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();
        $workspace = $this->withToken((string) $login->json('token'))
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/workspaces/first', ['name' => 'Support Management Fixture'])
            ->assertCreated();
        $this->artisan('atlas:support:open', [
            'workspace-id' => (string) $workspace->json('workspace_id'),
            'requester-email' => $email,
            'category' => 'Product',
            'severity' => 'P2',
            'summary-code' => 'product.management-fixture',
            '--reason' => 'Recette action Support',
        ])->assertSuccessful();
        $reference = (string) DB::table('operations.support_cases')->value('reference');
        $caseId = (string) DB::table('operations.support_cases')->value('id');
        $this->grant($email, [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::SUPPORT_READ,
            OperatorPermissionCatalog::SUPPORT_MANAGE,
        ]);
        $enrollment = app(EnrollOperatorMfaHandler::class)->handle($userId, $email, 'Atlas Back-office Test', 'Recette action Support');
        $operatorLogin = $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'mfa_code' => $enrollment['recovery_codes'][0],
        ])->assertOk();

        return [
            'email' => $email,
            'user_id' => $userId,
            'token' => (string) $operatorLogin->json('token'),
            'session_id' => (string) $operatorLogin->json('session_id'),
            'reference' => $reference,
            'case_id' => $caseId,
        ];
    }

    /** @param list<string> $permissions */
    private function grant(string $email, array $permissions): void
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Recette des gates Support',
        ])->assertSuccessful();
    }
}
