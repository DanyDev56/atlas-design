<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\RunsMvpCommercialFlow;

final class OperatorBetaCohortTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;
    use RunsMvpCommercialFlow;

    public function test_cohort_derives_activation_and_exposes_only_pseudonymized_diagnostics(): void
    {
        $owner = $this->onboardOwner($this, 'beta-cohort@example.test');
        $this->artisan('atlas:beta:enroll', [
            'beta-code' => 'BETA-001',
            'user-id' => $owner['user_id'],
            'workspace-id' => $owner['workspace_id'],
            '--packaging' => 'Atlas-Solo@1',
            '--segment' => 'primary',
            '--channel' => 'community',
            '--invited-at' => now()->subDays(5)->toIso8601String(),
            '--reason' => 'Prepare deterministic beta cohort fixture',
        ])->assertSuccessful();

        $flow = $this->runMvpJ2ThroughPayment($this, $owner);
        DB::table('billing.quotes')->where('id', $flow['quote_id'])->update([
            'sent_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        DB::table('crm.opportunities')
            ->where('workspace_id', $owner['workspace_id'])
            ->update(['updated_at' => now()->subDay()]);

        $this->artisan('atlas:beta:review', [
            'beta-code' => 'BETA-001',
            'milestone' => 'J7',
            '--blockage' => 'pricing-review',
            '--support-minutes' => 25,
            '--next-action' => 'schedule-pricing-decision',
            '--reason' => 'Record deterministic J7 review',
        ])->assertSuccessful();
        $this->artisan('atlas:beta:decision', [
            'beta-code' => 'BETA-001',
            'decision' => 'TRIAL_COMMITTED',
            '--primary-reason' => 'context',
            '--preference' => 'Monthly',
            '--evidence-ref' => 'research:BETA-001:J7',
            '--reason' => 'Record deterministic pricing outcome',
        ])->assertSuccessful();

        $token = $this->operatorToken($owner, [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::BETA_READ,
            OperatorPermissionCatalog::METRICS_READ_PRODUCT,
        ]);

        $this->withToken($token)->getJson('/api/operator/beta/overview')
            ->assertOk()
            ->assertJsonPath('participant_count', 1)
            ->assertJsonPath('blocked_count', 1)
            ->assertJsonPath('decision_count', 1)
            ->assertJsonPath('funnel.6.stage', 'E6')
            ->assertJsonPath('funnel.6.reached', 1)
            ->assertJsonPath('pricing_cells.1.cell', 'P24');

        $list = $this->withToken($token)->getJson('/api/operator/beta/participants?stage=E6&cell=P19')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.beta_code', 'BETA-001')
            ->assertJsonPath('items.0.current_stage', 'E6')
            ->assertJsonPath('items.0.latest_review.support_minutes', 25)
            ->assertJsonMissingPath('items.0.user_id')
            ->assertJsonMissingPath('items.0.workspace_id');
        self::assertStringNotContainsString('beta-cohort@example.test', $list->getContent());

        $this->withToken($token)->getJson('/api/operator/beta/participants/BETA-001')
            ->assertOk()
            ->assertJsonPath('participant.beta_code', 'BETA-001')
            ->assertJsonStructure(['diagnostic' => ['analytics', 'business_health', 'source']])
            ->assertJsonMissingPath('participant.user_id')
            ->assertJsonMissingPath('participant.workspace_id');

        $this->withToken($token)->getJson('/api/operator/overview')
            ->assertOk()
            ->assertJsonPath('cards.5.key', 'beta')
            ->assertJsonPath('cards.5.status', 'Available')
            ->assertJsonPath('cards.5.values.0.value', 1);

        $this->assertDatabaseHas('operations.operator_audit_entries', [
            'action' => 'operator.beta.diagnostic-read',
            'permission' => OperatorPermissionCatalog::BETA_READ,
        ]);
    }

    public function test_pricing_cell_is_immutable_and_product_metrics_need_their_exact_grant(): void
    {
        $owner = $this->onboardOwner($this, 'beta-permission@example.test');
        $this->artisan('atlas:beta:enroll', [
            'beta-code' => 'BETA-001',
            'user-id' => $owner['user_id'],
            'workspace-id' => $owner['workspace_id'],
            '--cell' => 'P29',
            '--reason' => 'Prepare permission fixture',
        ])->assertSuccessful();
        $token = $this->operatorToken($owner, [
            OperatorPermissionCatalog::BACKOFFICE_ACCESS,
            OperatorPermissionCatalog::DASHBOARD_READ,
            OperatorPermissionCatalog::BETA_READ,
        ]);

        $this->withToken($token)->getJson('/api/operator/beta/participants')->assertOk();
        $this->withToken($token)->getJson('/api/operator/beta/overview')
            ->assertForbidden()
            ->assertJsonPath('error', 'OperatorForbidden');

        DB::statement('SAVEPOINT immutable_pricing_cell');
        try {
            DB::table('operations.beta_participants')->where('beta_code', 'BETA-001')->update(['pricing_cell' => 'P19']);
            self::fail('Pricing cell mutation should be rejected.');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT immutable_pricing_cell');
        }
        $this->assertDatabaseHas('operations.beta_participants', ['beta_code' => 'BETA-001', 'pricing_cell' => 'P29']);

        $this->artisan('atlas:beta:review', [
            'beta-code' => 'BETA-001',
            'milestone' => 'J2',
            '--next-action' => 'follow-up',
            '--reason' => 'Prepare immutable research evidence',
        ])->assertSuccessful();
        DB::statement('SAVEPOINT immutable_beta_review');
        try {
            DB::table('operations.beta_reviews')->update(['support_minutes' => 99]);
            self::fail('Beta review mutation should be rejected.');
        } catch (\Throwable) {
            DB::statement('ROLLBACK TO SAVEPOINT immutable_beta_review');
        }
        $this->assertDatabaseHas('operations.beta_reviews', ['support_minutes' => 0]);
    }

    /** @param list<string> $permissions */
    private function operatorToken(array $owner, array $permissions): string
    {
        $email = (string) DB::table('identity.users')->where('id', $owner['user_id'])->value('email');
        $this->artisan('atlas:operator:grant', [
            'email' => $email,
            '--permissions' => implode(',', $permissions),
            '--reason' => 'Prepare beta operator test',
        ])->assertSuccessful();
        $login = $this->postJson('/api/operator/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();

        return (string) $login->json('token');
    }
}
