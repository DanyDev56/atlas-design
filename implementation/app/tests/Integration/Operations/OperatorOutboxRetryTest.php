<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Application\OutboxRetryActionException;
use Atlas\Modules\Operations\Application\RetryManagedOutboxMessageHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorOutboxRetryTest extends IntegrationTestCase
{
    public function test_operator_previews_releases_and_idempotently_replays_one_dead_letter(): void
    {
        $this->enableActions();
        $context = $this->context('outbox-retry@example.test');
        $eventId = $this->deadLetter();

        $this->withToken($context['token'])
            ->getJson('/api/operator/overview/outbox?status=DeadLetter')
            ->assertOk()
            ->assertJsonPath('items.0.event_id', $eventId)
            ->assertJsonMissingPath('items.0.payload')
            ->assertJsonMissingPath('items.0.last_error');

        $proposal = ['reason_code' => 'outbox.cause-corrected'];
        $preview = $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertOk()
            ->assertJsonPath('current.status', 'DeadLetter')
            ->assertJsonPath('current.attempts', 5)
            ->assertJsonPath('proposed.status', 'Pending')
            ->assertJsonPath('effects.2', 'Consommateurs déjà confirmés ignorés grâce aux reçus Inbox');
        $key = (string) Str::uuid();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];

        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/outbox/'.$eventId.'/retry', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'Pending')
            ->assertJsonPath('attempts', 0)
            ->assertJsonPath('replayed', false);
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/outbox/'.$eventId.'/retry', $payload)
            ->assertOk()
            ->assertJsonPath('replayed', true);

        $this->assertDatabaseHas('platform.outbox_messages', [
            'event_id' => $eventId,
            'attempts' => 0,
            'last_error' => null,
            'failed_at' => null,
        ]);
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.outbox.dead-letter-retried',
            'result' => 'Succeeded',
            'permission' => OperatorPermissionCatalog::OUTBOX_RETRY,
        ]);
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.outbox-retry.replayed',
            'result' => 'Replayed',
        ]);
    }

    public function test_feature_permission_step_up_and_dead_letter_state_are_independent_gates(): void
    {
        $context = $this->context('outbox-gates@example.test');
        $eventId = $this->deadLetter();
        $proposal = ['reason_code' => 'outbox.cause-corrected'];

        $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorActionsDisabled');

        $this->enableActions();
        $this->grant($context['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::OUTBOX_READ,
        ]);
        $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');

        $this->grant($context['email'], $this->permissions());
        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update(['step_up_at' => null]);
        $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorStepUpRequired');

        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update(['step_up_at' => now('UTC')]);
        $pending = $this->deadLetter();
        DB::table('platform.outbox_messages')->where('event_id', $pending)->update(['failed_at' => null]);
        $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$pending.'/retry-preview', $proposal)
            ->assertConflict()
            ->assertJsonPath('error', 'OutboxMessageNotDeadLetter');
    }

    public function test_stale_preview_conflict_authority_change_and_audit_failure_are_safe(): void
    {
        $this->enableActions();
        $context = $this->context('outbox-conflicts@example.test');
        $eventId = $this->deadLetter();
        $proposal = ['reason_code' => 'outbox.cause-corrected'];
        $preview = $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertOk();
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];

        DB::table('platform.outbox_messages')->where('event_id', $eventId)->update(['attempts' => 6]);
        $this->withToken($context['token'])->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson('/api/operator/outbox/'.$eventId.'/retry', $payload)
            ->assertConflict()
            ->assertJsonPath('error', 'OutboxRetryPreviewStale');

        $freshPreview = $this->withToken($context['token'])
            ->postJson('/api/operator/outbox/'.$eventId.'/retry-preview', $proposal)
            ->assertOk();
        $key = (string) Str::uuid();
        $freshPayload = [...$proposal, 'preview_fingerprint' => (string) $freshPreview->json('preview_fingerprint')];
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/outbox/'.$eventId.'/retry', $freshPayload)
            ->assertOk();
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)
            ->patchJson('/api/operator/outbox/'.$eventId.'/retry', [
                ...$freshPayload,
                'reason_code' => 'outbox.provider-recovered',
            ])
            ->assertConflict()
            ->assertJsonPath('error', 'OperatorIdempotencyConflict');

        $authorityEvent = $this->deadLetter();
        $authorityPreview = app(RetryManagedOutboxMessageHandler::class)->preview($authorityEvent, 'outbox.cause-corrected');
        DB::table('operations.operator_grants')->where('user_id', $context['user_id'])->update(['status' => 'Revoked', 'revoked_at' => now('UTC')]);
        try {
            app(RetryManagedOutboxMessageHandler::class)->apply(
                $authorityEvent,
                $context['user_id'],
                $context['session_id'],
                'outbox.cause-corrected',
                (string) $authorityPreview['preview_fingerprint'],
                (string) Str::uuid(),
                null,
            );
            self::fail('A revoked authority must not release a dead-letter.');
        } catch (OutboxRetryActionException $exception) {
            self::assertSame('OperatorAuthorityChanged', $exception->errorCode);
        }
        self::assertNotNull(DB::table('platform.outbox_messages')->where('event_id', $authorityEvent)->value('failed_at'));

        $rollback = $this->context('outbox-rollback@example.test');
        $rollbackEvent = $this->deadLetter();
        $rollbackPreview = app(RetryManagedOutboxMessageHandler::class)->preview($rollbackEvent, 'outbox.configuration-restored');
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION operations.fail_operator_audit_insert_for_test()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'simulated operator audit failure';
            END;
            $$;

            CREATE TRIGGER operations_operator_audit_fail_insert_test
            BEFORE INSERT ON operations.operator_audit_entries
            FOR EACH ROW
            EXECUTE FUNCTION operations.fail_operator_audit_insert_for_test();
            SQL);
        $auditFailed = false;
        try {
            app(RetryManagedOutboxMessageHandler::class)->apply(
                $rollbackEvent,
                $rollback['user_id'],
                $rollback['session_id'],
                'outbox.configuration-restored',
                (string) $rollbackPreview['preview_fingerprint'],
                (string) Str::uuid(),
                null,
            );
            self::fail('An audit failure must roll back the release.');
        } catch (\Throwable) {
            $auditFailed = true;
        } finally {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS operations_operator_audit_fail_insert_test ON operations.operator_audit_entries;
                DROP FUNCTION IF EXISTS operations.fail_operator_audit_insert_for_test();
                SQL);
        }
        self::assertTrue($auditFailed);
        $rollbackRow = DB::table('platform.outbox_messages')->where('event_id', $rollbackEvent)->first();
        self::assertSame(5, (int) $rollbackRow->attempts);
        self::assertNotNull($rollbackRow->failed_at);
        self::assertNotNull($rollbackRow->last_error);
        self::assertSame(0, DB::table('operations.operator_action_idempotency')->where('action_scope', 'outbox.retry:'.$rollbackEvent)->count());
    }

    /** @return array{email: string, user_id: string, token: string, session_id: string} */
    private function context(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Outbox Operator',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', [
            'user_id' => $userId,
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();
        $this->grant($email, $this->permissions());
        $enrollment = app(EnrollOperatorMfaHandler::class)->handle($userId, $email, 'Atlas Back-office Test', 'Recette reprise Outbox');
        $login = $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
            'mfa_code' => $enrollment['recovery_codes'][0],
        ])->assertOk();

        return [
            'email' => $email,
            'user_id' => $userId,
            'token' => (string) $login->json('token'),
            'session_id' => (string) $login->json('session_id'),
        ];
    }

    private function deadLetter(): string
    {
        $eventId = (string) Str::uuid();
        DB::table('platform.outbox_messages')->insert([
            'id' => (string) Str::uuid(),
            'event_id' => $eventId,
            'event_type' => 'test.operator-retry',
            'payload' => json_encode(['fixture' => 'dead-letter'], JSON_THROW_ON_ERROR),
            'occurred_at' => now('UTC')->subMinutes(10),
            'correlation_id' => null,
            'causation_id' => null,
            'schema_version' => 1,
            'created_at' => now('UTC')->subMinutes(10),
            'dispatched_at' => null,
            'attempts' => 5,
            'available_at' => now('UTC')->subMinutes(5),
            'last_error' => 'RuntimeException',
            'failed_at' => now('UTC')->subMinute(),
        ]);

        return $eventId;
    }

    private function enableActions(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
    }

    /** @return list<string> */
    private function permissions(): array
    {
        return [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::OUTBOX_READ,
            OperatorPermissionCatalog::OUTBOX_RETRY,
        ];
    }

    /** @param list<string> $permissions */
    private function grant(string $email, array $permissions): void
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Recette reprise ciblée Outbox',
        ])->assertSuccessful();
    }
}
