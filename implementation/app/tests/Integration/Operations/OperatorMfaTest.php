<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Domain\OperatorRecoveryCodes;
use Atlas\Modules\Operations\Domain\TotpAuthenticator;
use Atlas\Platform\Laravel\Http\Middleware\RequireRecentOperatorStepUpMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorMfaTest extends IntegrationTestCase
{
    public function test_enrolled_operator_requires_totp_and_a_timestep_cannot_be_replayed(): void
    {
        config()->set('operations.backoffice.require_mfa', true);
        $userId = $this->createVerifiedAccount('totp-operator@example.test');
        $this->grant('totp-operator@example.test');
        $enrollment = $this->enroll($userId, 'totp-operator@example.test');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $code = app(TotpAuthenticator::class)->code($enrollment['secret'], $now);

        $this->postJson('/api/operator/auth/login', [
            'email' => 'totp-operator@example.test',
            'password' => 'password123',
        ])->assertUnauthorized();

        $login = $this->postJson('/api/operator/auth/login', [
            'email' => 'totp-operator@example.test',
            'password' => 'password123',
            'mfa_code' => $code,
        ])->assertOk()
            ->assertJsonPath('authentication_strength', 'PasswordTotp');

        $this->assertNotNull($login->json('mfa_verified_at'));
        $this->assertNotNull($login->json('step_up_expires_at'));
        $stored = DB::table('operations.operator_mfa_credentials')->where('user_id', $userId)->first();
        $this->assertNotNull($stored);
        $this->assertNotSame($enrollment['secret'], $stored->secret_ciphertext);

        $this->postJson('/api/operator/auth/login', [
            'email' => 'totp-operator@example.test',
            'password' => 'password123',
            'mfa_code' => $code,
        ])->assertUnauthorized();
    }

    public function test_recovery_codes_are_one_time_and_can_refresh_step_up(): void
    {
        config()->set('operations.backoffice.require_mfa', true);
        $userId = $this->createVerifiedAccount('recovery-operator@example.test');
        $this->grant('recovery-operator@example.test');
        $enrollment = $this->enroll($userId, 'recovery-operator@example.test');

        $login = $this->postJson('/api/operator/auth/login', [
            'email' => 'recovery-operator@example.test',
            'password' => 'password123',
            'mfa_code' => $enrollment['recovery_codes'][0],
        ])->assertOk()
            ->assertJsonPath('authentication_strength', 'PasswordRecovery');
        $token = (string) $login->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/operator/auth/session/elevate', [
                'email' => 'recovery-operator@example.test',
                'password' => 'password123',
                'mfa_code' => $enrollment['recovery_codes'][1],
            ])->assertOk()
            ->assertJsonPath('authentication_strength', 'PasswordRecovery');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/operator/session/context')
            ->assertOk()
            ->assertJsonPath('authentication_strength', 'PasswordRecovery')
            ->assertJsonPath('mfa_verified_at', fn ($value): bool => is_string($value) && $value !== '')
            ->assertJsonPath('step_up_expires_at', fn ($value): bool => is_string($value) && $value !== '');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/operator/auth/session/elevate', [
                'email' => 'recovery-operator@example.test',
                'password' => 'password123',
                'mfa_code' => $enrollment['recovery_codes'][1],
            ])->assertUnauthorized()
            ->assertJsonPath('error', 'OperatorStepUpFailed');

        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.session.elevated',
            'result' => 'Succeeded',
        ]);
        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.session.elevation-denied',
            'result' => 'Denied',
        ]);
    }

    public function test_recovery_code_with_six_digits_is_not_misclassified_as_totp(): void
    {
        config()->set('operations.backoffice.require_mfa', true);
        $userId = $this->createVerifiedAccount('numeric-recovery@example.test');
        $this->grant('numeric-recovery@example.test');
        $this->enroll($userId, 'numeric-recovery@example.test');
        $recoveryCode = 'AB12-CD34-EF56';

        DB::table('operations.operator_mfa_credentials')
            ->where('user_id', $userId)
            ->update([
                'recovery_code_hashes' => json_encode([
                    app(OperatorRecoveryCodes::class)->hash($recoveryCode),
                ], JSON_THROW_ON_ERROR),
            ]);

        $this->postJson('/api/operator/auth/login', [
            'email' => 'numeric-recovery@example.test',
            'password' => 'password123',
            'mfa_code' => $recoveryCode,
        ])->assertOk()
            ->assertJsonPath('authentication_strength', 'PasswordRecovery');
    }

    public function test_rotating_or_disabling_mfa_revokes_existing_operator_sessions(): void
    {
        config()->set('operations.backoffice.require_mfa', true);
        $userId = $this->createVerifiedAccount('rotation-operator@example.test');
        $this->grant('rotation-operator@example.test');
        $firstEnrollment = $this->enroll($userId, 'rotation-operator@example.test');
        $login = $this->postJson('/api/operator/auth/login', [
            'email' => 'rotation-operator@example.test',
            'password' => 'password123',
            'mfa_code' => $firstEnrollment['recovery_codes'][0],
        ])->assertOk();
        $token = (string) $login->json('token');

        $this->enroll($userId, 'rotation-operator@example.test', 'Rotation de sécurité');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/operator/session/context')
            ->assertUnauthorized();

        $this->artisan('atlas:operator:mfa:disable', [
            'email' => 'rotation-operator@example.test',
            '--reason' => 'Compromission simulée',
        ])->assertSuccessful();

        $this->assertDatabaseHas('operations.operator_mfa_credentials', [
            'user_id' => $userId,
            'status' => 'Disabled',
            'secret_ciphertext' => '',
        ]);
        $this->postJson('/api/operator/auth/login', [
            'email' => 'rotation-operator@example.test',
            'password' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_totp_external_access_remains_closed_without_explicit_risk_acceptance(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');
        config()->set('operations.backoffice.require_mfa', true);
        config()->set('operations.backoffice.allow_totp_external', false);

        $this->postJson('/api/operator/auth/login', [
            'email' => 'nobody@example.test',
            'password' => 'password123',
            'mfa_code' => '123456',
        ])->assertStatus(503)
            ->assertJsonPath('error', 'StrongAuthenticationRequired');
    }

    public function test_sensitive_operator_capabilities_require_a_recent_step_up(): void
    {
        config()->set('operations.backoffice.step_up_minutes', 10);
        $middleware = new RequireRecentOperatorStepUpMiddleware;
        $request = Request::create('/api/operator/sensitive-capability');

        $missing = $middleware->handle($request, static fn () => response()->json(['status' => 'Allowed']));
        $this->assertSame(403, $missing->getStatusCode());

        $request->attributes->set('operator_step_up_at', '-11 minutes');
        $expired = $middleware->handle($request, static fn () => response()->json(['status' => 'Allowed']));
        $this->assertSame(403, $expired->getStatusCode());

        $request->attributes->set('operator_step_up_at', 'now');
        $allowed = $middleware->handle($request, static fn () => response()->json(['status' => 'Allowed']));
        $this->assertSame(200, $allowed->getStatusCode());
    }

    private function createVerifiedAccount(string $email): string
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

        return $userId;
    }

    private function grant(string $email): void
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--reason' => 'Prepare MFA integration test',
        ])->assertSuccessful();
    }

    private function enroll(string $userId, string $email, string $reason = 'Initial MFA enrollment'): array
    {
        return app(EnrollOperatorMfaHandler::class)->handle(
            $userId,
            $email,
            'Atlas Back-office Test',
            $reason,
        );
    }
}
