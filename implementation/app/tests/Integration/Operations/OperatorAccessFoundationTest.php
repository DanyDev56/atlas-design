<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorAccessFoundationTest extends IntegrationTestCase
{
    public function test_operator_and_workspace_sessions_have_distinct_audiences_and_revocation_is_immediate(): void
    {
        [$userId, $clientToken] = $this->createVerifiedAccount('operator@example.test');

        $this->postJson('/api/operator/auth/login', [
            'email' => 'operator@example.test',
            'password' => 'password123',
        ])->assertUnauthorized()
            ->assertJsonPath('error', 'InvalidOperatorCredentials');

        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.session.open-denied',
            'result' => 'Denied',
        ]);

        $this->artisan('atlas:operator:grant', [
            'email' => 'operator@example.test',
            '--reason' => 'Bootstrap local operator foundation test',
        ])->assertSuccessful();

        $operatorLogin = $this->postJson('/api/operator/auth/login', [
            'email' => 'operator@example.test',
            'password' => 'password123',
        ]);
        $operatorLogin->assertOk()
            ->assertJsonPath('user_id', $userId)
            ->assertJsonPath('permissions.0', 'operations.backoffice.access');
        $operatorToken = (string) $operatorLogin->json('token');

        $this->withHeader('Authorization', 'Bearer '.$clientToken)
            ->getJson('/api/operator/session/context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'OperatorUnauthenticated');

        $this->withHeader('Authorization', 'Bearer '.$operatorToken)
            ->getJson('/api/auth/session/context')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthenticated');

        $this->withHeader('Authorization', 'Bearer '.$operatorToken)
            ->getJson('/api/operator/session/context')
            ->assertOk()
            ->assertJsonPath('read_only', true)
            ->assertJsonPath('permissions.0', 'operations.backoffice.access');

        $this->artisan('atlas:operator:revoke', [
            'email' => 'operator@example.test',
            '--reason' => 'Verify immediate grant reduction',
        ])->assertSuccessful();

        $this->withHeader('Authorization', 'Bearer '.$operatorToken)
            ->getJson('/api/operator/session/context')
            ->assertUnauthorized();

        $this->assertDatabaseHas('operations.operator_grants', [
            'user_id' => $userId,
            'status' => 'Revoked',
        ]);
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.grant.revoked',
            'result' => 'Succeeded',
        ]);
    }

    public function test_operator_logout_revokes_only_the_operator_session(): void
    {
        [, $clientToken] = $this->createVerifiedAccount('logout-operator@example.test');
        $this->artisan('atlas:operator:grant', [
            'email' => 'logout-operator@example.test',
            '--reason' => 'Prepare logout test',
        ])->assertSuccessful();

        $login = $this->postJson('/api/operator/auth/login', [
            'email' => 'logout-operator@example.test',
            'password' => 'password123',
        ])->assertOk();
        $operatorToken = (string) $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$operatorToken)
            ->postJson('/api/operator/auth/session/revoke', [])
            ->assertOk()
            ->assertJsonPath('status', 'Revoked');

        $this->withHeader('Authorization', 'Bearer '.$operatorToken)
            ->getJson('/api/operator/session/context')
            ->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$clientToken)
            ->getJson('/api/auth/session/context')
            ->assertOk();
    }

    public function test_backoffice_feature_gate_is_closed_by_default_when_disabled(): void
    {
        config()->set('operations.backoffice.enabled', false);

        $this->postJson('/api/operator/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'password123',
        ])->assertNotFound()
            ->assertJsonPath('error', 'BackofficeUnavailable');
    }

    public function test_operator_tokens_are_stored_only_as_hashes(): void
    {
        $this->createVerifiedAccount('hashed-operator@example.test');
        $this->artisan('atlas:operator:grant', [
            'email' => 'hashed-operator@example.test',
            '--reason' => 'Verify token storage',
        ])->assertSuccessful();

        $login = $this->postJson('/api/operator/auth/login', [
            'email' => 'hashed-operator@example.test',
            'password' => 'password123',
        ])->assertOk();
        $plainToken = (string) $login->json('token');

        $row = DB::table('operations.operator_sessions')->first();
        $this->assertNotNull($row);
        $this->assertNotSame($plainToken, $row->token_hash);
        $this->assertSame(PostgresOperatorSessionRepository::hashToken($plainToken), $row->token_hash);
    }

    public function test_operator_audit_is_append_only_at_database_level(): void
    {
        $this->postJson('/api/operator/auth/login', [
            'email' => 'unknown-operator@example.test',
            'password' => 'password123',
        ])->assertUnauthorized();

        $entry = DB::table('operations.operator_audit_entries')->first();
        $this->assertNotNull($entry);
        $this->assertFalse($this->auditMutationSucceeds(
            'UPDATE operations.operator_audit_entries SET result = ? WHERE id = ?',
            ['Altered', $entry->id],
        ));
        $this->assertFalse($this->auditMutationSucceeds(
            'DELETE FROM operations.operator_audit_entries WHERE id = ?',
            [$entry->id],
        ));
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'id' => $entry->id,
            'result' => 'Denied',
        ]);
    }

    /** @return array{string, string} */
    private function createVerifiedAccount(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Atlas Operator',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertCreated();

        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', [
            'user_id' => $userId,
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();

        $login = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk();

        return [$userId, (string) $login->json('token')];
    }

    /** @param list<string> $bindings */
    private function auditMutationSucceeds(string $sql, array $bindings): bool
    {
        DB::statement('SAVEPOINT operator_audit_append_only_test');

        try {
            DB::update($sql, $bindings);
            DB::statement('RELEASE SAVEPOINT operator_audit_append_only_test');

            return true;
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT operator_audit_append_only_test');

            return false;
        }
    }
}
