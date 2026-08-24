<?php

declare(strict_types=1);

namespace Tests\Integration\Retention;

use Atlas\Platform\Retention\Infrastructure\RetentionPurger;
use Atlas\Platform\Support\UuidGenerator;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class RetentionPurgerTest extends IntegrationTestCase
{
    public function test_purges_expired_and_revoked_sessions_keeps_active(): void
    {
        $userId = UuidGenerator::generate();
        $now = now();

        DB::table('identity.sessions')->insert([
            [
                'id' => UuidGenerator::generate(),
                'user_id' => $userId,
                'token_hash' => hash('sha256', 'expired'),
                'status' => 'Active',
                'expires_at' => $now->copy()->subDays(31)->toIso8601String(),
                'created_at' => $now->copy()->subDays(40)->toIso8601String(),
                'revoked_at' => null,
            ],
            [
                'id' => UuidGenerator::generate(),
                'user_id' => $userId,
                'token_hash' => hash('sha256', 'revoked'),
                'status' => 'Revoked',
                'expires_at' => $now->copy()->addDay()->toIso8601String(),
                'created_at' => $now->copy()->subDays(10)->toIso8601String(),
                'revoked_at' => $now->copy()->subDays(31)->toIso8601String(),
            ],
            [
                'id' => UuidGenerator::generate(),
                'user_id' => $userId,
                'token_hash' => hash('sha256', 'active'),
                'status' => 'Active',
                'expires_at' => $now->copy()->addDay()->toIso8601String(),
                'created_at' => $now->toIso8601String(),
                'revoked_at' => null,
            ],
        ]);

        $deleted = app(RetentionPurger::class)->purgeSessions();

        $this->assertSame(2, $deleted);
        $this->assertSame(1, DB::table('identity.sessions')->count());
        $this->assertSame(1, DB::table('identity.sessions')->where('token_hash', hash('sha256', 'active'))->count());
    }

    public function test_purges_old_operator_sessions_and_action_idempotency(): void
    {
        $now = now();
        $userId = UuidGenerator::generate();
        $grantId = UuidGenerator::generate();
        DB::table('operations.operator_grants')->insert([
            'id' => $grantId,
            'user_id' => $userId,
            'permissions' => json_encode(['operations.backoffice.access'], JSON_THROW_ON_ERROR),
            'status' => 'Active',
            'expires_at' => null,
            'version' => 1,
            'created_at' => $now->copy()->subDays(40)->toIso8601String(),
            'updated_at' => $now->toIso8601String(),
            'revoked_at' => null,
        ]);
        DB::table('operations.operator_sessions')->insert([
            'id' => UuidGenerator::generate(),
            'reference' => 'SES-A1B2C3D4E5F6',
            'user_id' => $userId,
            'grant_id' => $grantId,
            'token_hash' => hash('sha256', 'old-operator'),
            'status' => 'Revoked',
            'revision' => 2,
            'authentication_strength' => 'Totp',
            'mfa_verified_at' => $now->copy()->subDays(40)->toIso8601String(),
            'step_up_at' => $now->copy()->subDays(40)->toIso8601String(),
            'expires_at' => $now->copy()->subDays(31)->toIso8601String(),
            'created_at' => $now->copy()->subDays(40)->toIso8601String(),
            'revoked_at' => $now->copy()->subDays(31)->toIso8601String(),
        ]);
        DB::table('operations.operator_action_idempotency')->insert([
            'id' => UuidGenerator::generate(),
            'action_scope' => 'operator-session.revoke:SES-A1B2C3D4E5F6',
            'idempotency_key' => 'old-action-key',
            'request_fingerprint' => hash('sha256', 'old-action'),
            'response' => json_encode(['status' => 'Revoked'], JSON_THROW_ON_ERROR),
            'created_at' => $now->copy()->subDays(31)->toIso8601String(),
        ]);

        self::assertSame(1, app(RetentionPurger::class)->purgeSessions());
        self::assertSame(1, app(RetentionPurger::class)->purgeIdempotencyKeys());
        self::assertSame(0, DB::table('operations.operator_sessions')->count());
        self::assertSame(0, DB::table('operations.operator_action_idempotency')->count());
    }

    public function test_purges_old_dispatched_outbox_keeps_pending(): void
    {
        $now = now();

        DB::table('platform.outbox_messages')->insert([
            [
                'id' => UuidGenerator::generate(),
                'event_id' => UuidGenerator::generate(),
                'event_type' => 'test.old',
                'payload' => json_encode(['old' => true], JSON_THROW_ON_ERROR),
                'occurred_at' => $now->copy()->subDays(100)->toIso8601String(),
                'correlation_id' => null,
                'causation_id' => null,
                'schema_version' => 1,
                'created_at' => $now->copy()->subDays(100)->toIso8601String(),
                'dispatched_at' => $now->copy()->subDays(91)->toIso8601String(),
            ],
            [
                'id' => UuidGenerator::generate(),
                'event_id' => UuidGenerator::generate(),
                'event_type' => 'test.pending',
                'payload' => json_encode(['pending' => true], JSON_THROW_ON_ERROR),
                'occurred_at' => $now->toIso8601String(),
                'correlation_id' => null,
                'causation_id' => null,
                'schema_version' => 1,
                'created_at' => $now->toIso8601String(),
                'dispatched_at' => null,
            ],
        ]);

        $deleted = app(RetentionPurger::class)->purgeDispatchedOutbox();

        $this->assertSame(1, $deleted);
        $this->assertSame(1, DB::table('platform.outbox_messages')->count());
        $this->assertNull(DB::table('platform.outbox_messages')->value('dispatched_at'));
    }

    public function test_purges_stale_idempotency_keys(): void
    {
        $now = now();

        DB::table('identity.idempotency_keys')->insert([
            'scope' => 'test',
            'key' => 'old',
            'fingerprint' => 'fp-old',
            'response_payload' => null,
            'created_at' => $now->copy()->subDays(31)->toIso8601String(),
        ]);

        DB::table('crm.idempotency_keys')->insert([
            'scope' => 'test',
            'key' => 'recent',
            'fingerprint' => 'fp-recent',
            'response_payload' => null,
            'created_at' => $now->toIso8601String(),
        ]);

        $deleted = app(RetentionPurger::class)->purgeIdempotencyKeys();

        $this->assertSame(1, $deleted);
        $this->assertSame(0, DB::table('identity.idempotency_keys')->count());
        $this->assertSame(1, DB::table('crm.idempotency_keys')->count());
    }

    public function test_dry_run_does_not_delete_rows(): void
    {
        $now = now();

        DB::table('identity.sessions')->insert([
            'id' => UuidGenerator::generate(),
            'user_id' => UuidGenerator::generate(),
            'token_hash' => hash('sha256', 'dry-run'),
            'status' => 'Active',
            'expires_at' => $now->copy()->subDays(31)->toIso8601String(),
            'created_at' => $now->copy()->subDays(40)->toIso8601String(),
            'revoked_at' => null,
        ]);

        $result = app(RetentionPurger::class)->purgeAll(dryRun: true);

        $this->assertTrue($result->dryRun);
        $this->assertSame(1, $result->sessions);
        $this->assertSame(1, DB::table('identity.sessions')->count());
    }
}
