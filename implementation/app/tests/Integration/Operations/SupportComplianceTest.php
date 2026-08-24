<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class SupportComplianceTest extends IntegrationTestCase
{
    public function test_support_and_compliance_registries_are_structured_pseudonymized_and_read_only(): void
    {
        $account = $this->ownerWorkspace('support-owner@example.test');
        $this->artisan('atlas:support:open', [
            'workspace-id' => $account['workspace_id'],
            'requester-email' => $account['email'],
            'category' => 'Access',
            'severity' => 'P1',
            'summary-code' => 'login.blocked',
            '--reason' => 'Recette du registre support',
        ])->assertSuccessful();
        $this->artisan('atlas:compliance:request', [
            'workspace-id' => $account['workspace_id'],
            'requester-email' => $account['email'],
            'type' => 'Access',
            'summary-code' => 'rights.access-request',
            '--reason' => 'Recette de demande de droit',
        ])->assertSuccessful();
        $hash = hash('sha256', 'reviewed beta terms fixture');
        $this->artisan('atlas:compliance:policy', [
            'kind' => 'BetaTerms',
            'version' => 'beta-test-v1',
            'lifecycle' => 'Published',
            'sha256' => $hash,
            '--effective-at' => '2026-08-24T12:00:00+00:00',
            '--approval-ref' => 'LEGAL-TEST-001',
            '--reason' => 'Recette documentaire fictive',
        ])->assertSuccessful();
        $this->artisan('atlas:compliance:policy-proof', [
            'kind' => 'BetaTerms',
            'version' => 'beta-test-v1',
            'requester-email' => $account['email'],
            'proof-type' => 'Accepted',
            'evidence-ref' => 'PROOF-TEST-001',
            '--workspace-id' => $account['workspace_id'],
            '--reason' => 'Recette de preuve fictive',
        ])->assertSuccessful();
        $this->artisan('atlas:compliance:consent', [
            'requester-email' => $account['email'],
            'purpose' => 'Interview',
            'decision' => 'Granted',
            'evidence-ref' => 'CONSENT-TEST-001',
            '--workspace-id' => $account['workspace_id'],
            '--reason' => 'Recette consentement facultatif',
        ])->assertSuccessful();

        $token = $this->operatorToken($account['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::SUPPORT_READ,
            OperatorPermissionCatalog::COMPLIANCE_READ,
        ]);
        $support = $this->withToken($token)->getJson('/api/operator/overview/support')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonMissingPath('items.0.workspace_id')
            ->assertJsonMissingPath('items.0.requester_user_id');
        self::assertStringNotContainsString($account['email'], $support->getContent());
        self::assertStringNotContainsString($account['workspace_id'], $support->getContent());

        $data = $this->withToken($token)->getJson('/api/operator/overview/data-requests')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.request_type', 'Access')
            ->assertJsonPath('items.0.identity_verified', true)
            ->assertJsonPath('items.0.ownership_verified', true)
            ->assertJsonMissingPath('items.0.evidence_fingerprint');
        self::assertStringNotContainsString($account['email'], $data->getContent());

        $this->withToken($token)->getJson('/api/operator/overview/compliance')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.version', 'beta-test-v1')
            ->assertJsonPath('items.0.proof_count', 1)
            ->assertJsonPath('consent_counts.Interview', 1)
            ->assertJsonMissingPath('items.0.content_fingerprint')
            ->assertJsonMissingPath('items.0.approval_fingerprint');

        $this->withToken($token)->getJson('/api/operator/overview')
            ->assertOk()
            ->assertJsonPath('cards.7.status', 'Available')
            ->assertJsonPath('cards.7.values.0.value', 2)
            ->assertJsonPath('cards.8.values.0.value', 1);
    }

    public function test_published_policy_needs_approval_and_evidence_tables_are_append_only(): void
    {
        $account = $this->ownerWorkspace('evidence-owner@example.test');
        $this->artisan('atlas:compliance:policy', [
            'kind' => 'PrivacyNotice',
            'version' => 'privacy-test-v1',
            'lifecycle' => 'Published',
            'sha256' => hash('sha256', 'privacy fixture'),
            '--effective-at' => '2026-08-24T12:00:00+00:00',
            '--reason' => 'Publication volontairement invalide',
        ])->assertFailed();

        $this->artisan('atlas:support:open', [
            'workspace-id' => $account['workspace_id'],
            'requester-email' => $account['email'],
            'category' => 'Product',
            'severity' => 'P3',
            'summary-code' => 'question.product',
            '--reason' => 'Preuve append-only',
        ])->assertSuccessful();
        $eventId = (string) DB::table('operations.support_case_events')->value('id');

        $this->expectException(QueryException::class);
        DB::table('operations.support_case_events')->where('id', $eventId)->update(['detail_code' => 'tampered']);
    }

    public function test_support_and_compliance_permissions_are_independent(): void
    {
        $account = $this->ownerWorkspace('limited-support@example.test');
        $token = $this->operatorToken($account['email'], [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::SUPPORT_READ,
        ]);

        $this->withToken($token)->getJson('/api/operator/overview/support')->assertOk();
        $this->withToken($token)->getJson('/api/operator/overview/data-requests')
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');
    }

    public function test_response_targets_follow_business_time_and_draft_policy_cannot_receive_proof(): void
    {
        $account = $this->ownerWorkspace('support-calendar@example.test');
        $this->artisan('atlas:support:open', [
            'workspace-id' => $account['workspace_id'],
            'requester-email' => $account['email'],
            'category' => 'Security',
            'severity' => 'P0',
            'summary-code' => 'security.friday-incident',
            '--opened-at' => '2026-08-28T17:00:00+02:00',
            '--reason' => 'Recette des heures ouvrées',
        ])->assertSuccessful();
        $dueAt = new \DateTimeImmutable((string) DB::table('operations.support_cases')
            ->where('summary_code', 'security.friday-incident')
            ->value('response_due_at'));
        self::assertSame('2026-08-31T12:00:00+02:00', $dueAt->setTimezone(new \DateTimeZone('Europe/Paris'))->format(DATE_ATOM));

        $this->artisan('atlas:compliance:policy', [
            'kind' => 'PrivacyNotice',
            'version' => 'privacy-draft-v1',
            'lifecycle' => 'Draft',
            'sha256' => hash('sha256', 'draft privacy fixture'),
            '--reason' => 'Enregistrement du brouillon fictif',
        ])->assertSuccessful();
        $this->artisan('atlas:compliance:policy-proof', [
            'kind' => 'PrivacyNotice',
            'version' => 'privacy-draft-v1',
            'requester-email' => $account['email'],
            'proof-type' => 'Informed',
            'evidence-ref' => 'PROOF-DRAFT-001',
            '--workspace-id' => $account['workspace_id'],
            '--reason' => 'Preuve volontairement invalide',
        ])->assertFailed();
        self::assertSame(0, DB::table('operations.policy_acknowledgements')->count());
    }

    /** @return array{email: string, workspace_id: string} */
    private function ownerWorkspace(string $email): array
    {
        $register = $this->postJson('/api/auth/register', [
            'email' => $email,
            'display_name' => 'Support Owner',
            'password' => 'password123',
            'debug_verification_token' => true,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->postJson('/api/auth/verify-email', [
            'user_id' => (string) $register->json('user_id'),
            'token' => (string) $register->json('verification_token'),
        ])->assertOk();
        $login = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();
        $workspace = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->withToken((string) $login->json('token'))
            ->postJson('/api/workspaces/first', ['name' => 'Support Fixture'])
            ->assertCreated();

        return ['email' => $email, 'workspace_id' => (string) $workspace->json('workspace_id')];
    }

    /** @param list<string> $permissions */
    private function operatorToken(string $email, array $permissions): string
    {
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Recette Support et Conformité',
        ])->assertSuccessful();

        return (string) $this->postJson('/api/operator/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ])->assertOk()->json('token');
    }
}
