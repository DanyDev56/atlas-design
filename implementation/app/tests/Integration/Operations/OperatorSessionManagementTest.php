<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Application\OperatorSessionActionException;
use Atlas\Modules\Operations\Application\RevokeManagedOperatorSessionHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorSessionManagementTest extends IntegrationTestCase
{
    public function test_security_reviewer_lists_previews_revokes_and_replays_a_target_session(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $context = $this->context('session-reviewer@example.test');

        $page = $this->withToken($context['actor_token'])
            ->getJson('/api/operator/security/sessions?status=Active')
            ->assertOk()
            ->assertJsonCount(2, 'items')
            ->assertJsonMissingPath('items.0.user_id');
        $items = collect($page->json('items'));
        $current = $items->firstWhere('current', true);
        $target = $items->firstWhere('current', false);
        self::assertIsArray($current);
        self::assertIsArray($target);

        $proposal = ['expected_revision' => $target['revision'], 'reason_code' => 'session.security-review'];
        $preview = $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$target['reference'].'/preview', $proposal)
            ->assertOk()
            ->assertJsonPath('current.status', 'Active')
            ->assertJsonPath('proposed.status', 'Revoked')
            ->assertJsonPath('effects.1', 'Sessions Workspace inchangées');
        $key = (string) Str::uuid();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];

        $this->withToken($context['actor_token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/security/sessions/'.$target['reference'], $payload)
            ->assertOk()
            ->assertJsonPath('status', 'Revoked')
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', false);
        $this->withToken($context['actor_token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/security/sessions/'.$target['reference'], $payload)
            ->assertOk()
            ->assertJsonPath('revision', 2)
            ->assertJsonPath('replayed', true);

        $this->withToken($context['target_token'])->getJson('/api/operator/session/context')->assertUnauthorized();
        $this->withToken($context['actor_token'])->getJson('/api/operator/session/context')->assertOk();
        $this->withToken($context['workspace_token'])->getJson('/api/auth/session/context')->assertOk();
        $this->assertDatabaseHas('operations.operator_sessions', ['reference' => $target['reference'], 'status' => 'Revoked', 'revision' => 2]);
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.session.target-revoked', 'result' => 'Succeeded']);
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.session-revoke.replayed', 'result' => 'Replayed']);
    }

    public function test_feature_permission_step_up_and_self_revocation_are_independent_gates(): void
    {
        $context = $this->context('session-gates@example.test');
        $target = $this->target($context['actor_token']);
        $proposal = ['expected_revision' => $target['revision'], 'reason_code' => 'session.security-review'];

        $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$target['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorActionsDisabled');

        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $this->grant($context['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::SESSIONS_READ,
        ]);
        $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$target['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');

        $this->grant($context['email'], $this->permissions());
        DB::table('operations.operator_sessions')->where('id', $context['actor_session_id'])->update(['step_up_at' => null]);
        $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$target['reference'].'/preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorStepUpRequired');

        DB::table('operations.operator_sessions')->where('id', $context['actor_session_id'])->update(['step_up_at' => now('UTC')]);
        $actorReference = (string) DB::table('operations.operator_sessions')->where('id', $context['actor_session_id'])->value('reference');
        $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$actorReference.'/preview', [
                'expected_revision' => 1,
                'reason_code' => 'session.security-review',
            ])
            ->assertConflict()
            ->assertJsonPath('error', 'OperatorSelfSessionRevocationForbidden');
    }

    public function test_stale_revision_idempotency_conflict_and_concurrent_authority_revocation_are_rejected(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
        $context = $this->context('session-conflicts@example.test');
        $target = $this->target($context['actor_token']);
        $proposal = ['expected_revision' => $target['revision'], 'reason_code' => 'session.security-review'];
        $preview = $this->withToken($context['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$target['reference'].'/preview', $proposal)
            ->assertOk();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];

        DB::table('operations.operator_sessions')->where('reference', $target['reference'])->update(['revision' => 2]);
        $this->withToken($context['actor_token'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson('/api/operator/security/sessions/'.$target['reference'], $payload)
            ->assertConflict()
            ->assertJsonPath('error', 'OperatorSessionRevisionConflict');
        DB::table('operations.operator_sessions')->where('reference', $target['reference'])->update(['revision' => 1]);

        $key = (string) Str::uuid();
        $this->withToken($context['actor_token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/security/sessions/'.$target['reference'], $payload)
            ->assertOk();
        $this->withToken($context['actor_token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/security/sessions/'.$target['reference'], [
                ...$payload,
                'reason_code' => 'session.compromised',
            ])
            ->assertConflict()
            ->assertJsonPath('error', 'OperatorIdempotencyConflict');

        $fresh = $this->context('session-revoked-authority@example.test');
        $freshTarget = $this->target($fresh['actor_token']);
        $freshProposal = ['expected_revision' => $freshTarget['revision'], 'reason_code' => 'session.security-review'];
        $freshPreview = $this->withToken($fresh['actor_token'])
            ->postJson('/api/operator/security/sessions/'.$freshTarget['reference'].'/preview', $freshProposal)
            ->assertOk();
        DB::table('operations.operator_sessions')->where('id', $fresh['actor_session_id'])->update(['status' => 'Revoked', 'revoked_at' => now('UTC')]);

        try {
            app(RevokeManagedOperatorSessionHandler::class)->apply(
                $freshTarget['reference'],
                $fresh['user_id'],
                $fresh['actor_session_id'],
                $freshTarget['revision'],
                'session.security-review',
                (string) $freshPreview->json('preview_fingerprint'),
                (string) Str::uuid(),
                null,
            );
            self::fail('A revoked actor session must not revoke another session.');
        } catch (OperatorSessionActionException $exception) {
            self::assertSame('OperatorAuthorityChanged', $exception->errorCode);
        }
        $this->assertDatabaseHas('operations.operator_sessions', ['reference' => $freshTarget['reference'], 'status' => 'Active']);
    }

    /** @return array{email: string, user_id: string, workspace_token: string, actor_token: string, actor_session_id: string, target_token: string} */
    private function context(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Session Reviewer',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', [
            'user_id' => $userId,
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();
        $workspaceLogin = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();
        $this->grant($email, $this->permissions());
        $enrollment = app(EnrollOperatorMfaHandler::class)->handle($userId, $email, 'Atlas Back-office Test', 'Recette révocation ciblée');
        $actor = $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'mfa_code' => $enrollment['recovery_codes'][0],
        ])->assertOk();
        $target = $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'mfa_code' => $enrollment['recovery_codes'][1],
        ])->assertOk();

        return [
            'email' => $email,
            'user_id' => $userId,
            'workspace_token' => (string) $workspaceLogin->json('token'),
            'actor_token' => (string) $actor->json('token'),
            'actor_session_id' => (string) $actor->json('session_id'),
            'target_token' => (string) $target->json('token'),
        ];
    }

    /** @return array<string, mixed> */
    private function target(string $actorToken): array
    {
        $items = $this->withToken($actorToken)
            ->getJson('/api/operator/security/sessions?status=Active')
            ->assertOk()
            ->json('items');
        $target = collect($items)->firstWhere('current', false);
        self::assertIsArray($target);

        return $target;
    }

    /** @return list<string> */
    private function permissions(): array
    {
        return [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::SESSIONS_READ,
            OperatorPermissionCatalog::SESSIONS_REVOKE,
        ];
    }

    /** @param list<string> $permissions */
    private function grant(string $email, array $permissions): void
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Recette révocation de session',
        ])->assertSuccessful();
    }
}
