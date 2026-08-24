<?php

declare(strict_types=1);

namespace Tests\Integration\Operations;

use Atlas\Modules\Operations\Application\EnrollOperatorMfaHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Subscriptions\Contracts\RecurringBillingReconciliationGateway;
use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
use Atlas\Modules\Subscriptions\Domain\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Integration\IntegrationTestCase;

final class OperatorSubscriptionReconciliationTest extends IntegrationTestCase
{
    public function test_operator_previews_applies_and_idempotently_replays_a_targeted_reconciliation(): void
    {
        $this->enableActions();
        config()->set('subscriptions.gateway', 'fake');
        $context = $this->context('subscription-reconciliation@example.test');
        $fixture = $this->subscription('Unknown');
        $this->app->instance(RecurringBillingReconciliationGateway::class, new DriftedReconciliationGateway);

        $proposal = ['expected_version' => 1, 'reason_code' => 'subscription.provider-drift-reviewed'];
        $preview = $this->withToken($context['token'])->postJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation-preview', $proposal)
            ->assertOk()->assertJsonPath('aligned', false)
            ->assertJsonPath('changes.0.field', 'billing_environment')
            ->assertJsonPath('changes.0.current', 'Unknown')
            ->assertJsonPath('changes.0.provider', 'Sandbox')
            ->assertJsonPath('changes.1.field', 'status');
        $payload = [...$proposal, 'preview_fingerprint' => (string) $preview->json('preview_fingerprint')];
        $key = (string) Str::uuid();

        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)->patchJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation', $payload)
            ->assertOk()->assertJsonPath('status', 'PastDue')->assertJsonPath('version', 2)->assertJsonPath('replayed', false);
        $this->withToken($context['token'])->withHeader('Idempotency-Key', $key)->patchJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation', $payload)
            ->assertOk()->assertJsonPath('replayed', true);

        self::assertSame('PastDue', DB::table('subscriptions.recurring_subscriptions')->where('id', $fixture['id'])->value('status'));
        self::assertSame('Sandbox', DB::table('subscriptions.recurring_subscriptions')->where('id', $fixture['id'])->value('billing_environment'));
        self::assertSame(2, DB::table('subscriptions.recurring_subscriptions')->where('id', $fixture['id'])->value('version'));
        self::assertSame(1, DB::table('operations.operator_action_idempotency')->where('action_scope', 'subscription.reconcile:'.$fixture['reference'])->count());
        $this->assertDatabaseHas('operations.operator_audit_entries', ['action' => 'operator.subscription.reconciled', 'permission' => OperatorPermissionCatalog::SUBSCRIPTIONS_RECONCILE]);
    }

    public function test_actions_permission_step_up_environment_and_version_are_enforced(): void
    {
        config()->set('subscriptions.gateway', 'fake');
        $context = $this->context('subscription-gates@example.test');
        $fixture = $this->subscription();
        $this->app->instance(RecurringBillingReconciliationGateway::class, new DriftedReconciliationGateway);
        $proposal = ['expected_version' => 1, 'reason_code' => 'subscription.provider-drift-reviewed'];

        $this->withToken($context['token'])->postJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation-preview', $proposal)
            ->assertForbidden()->assertJsonPath('error', 'OperatorActionsDisabled');
        $this->enableActions();
        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update(['step_up_at' => null]);
        $this->withToken($context['token'])->postJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation-preview', $proposal)
            ->assertForbidden()->assertJsonPath('error', 'OperatorStepUpRequired');
        DB::table('operations.operator_sessions')->where('id', $context['session_id'])->update(['step_up_at' => now('UTC')]);
        DB::table('subscriptions.recurring_subscriptions')->where('id', $fixture['id'])->update(['billing_environment' => 'Live']);
        $this->withToken($context['token'])->postJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation-preview', $proposal)
            ->assertConflict()->assertJsonPath('error', 'SubscriptionEnvironmentMismatch');
        DB::table('subscriptions.recurring_subscriptions')->where('id', $fixture['id'])->update(['billing_environment' => 'Sandbox', 'version' => 2]);
        $this->withToken($context['token'])->postJson('/api/operator/subscriptions/'.$fixture['reference'].'/reconciliation-preview', $proposal)
            ->assertConflict()->assertJsonPath('error', 'SubscriptionVersionConflict');
    }

    /** @return array{email:string,user_id:string,token:string,session_id:string} */
    private function context(string $email): array
    {
        config()->set('platform.development.debug_verification_tokens', true);
        $register = $this->postJson('/api/auth/register', ['email' => $email, 'display_name' => 'Subscription Operator', 'password' => 'password123', 'debug_verification_token' => true], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $userId = (string) $register->json('user_id');
        $this->postJson('/api/auth/verify-email', ['user_id' => $userId, 'token' => (string) $register->json('verification_token')])->assertOk();
        $this->artisan('atlas:operator:grant', ['email' => $email, '--permissions' => implode(',', [OperatorPermissionCatalog::BACKOFFICE_ACCESS, OperatorPermissionCatalog::SUBSCRIPTIONS_READ, OperatorPermissionCatalog::SUBSCRIPTIONS_RECONCILE]), '--reason' => 'Recette réconciliation ciblée'])->assertSuccessful();
        $enrollment = app(EnrollOperatorMfaHandler::class)->handle($userId, $email, 'Atlas Back-office Test', 'Recette réconciliation');
        $login = $this->postJson('/api/operator/auth/login', ['email' => $email, 'password' => 'password123', 'mfa_code' => $enrollment['recovery_codes'][0]])->assertOk();
        return ['email' => $email, 'user_id' => $userId, 'token' => (string) $login->json('token'), 'session_id' => (string) $login->json('session_id')];
    }

    /** @return array{id:string,reference:string} */
    private function subscription(string $environment = 'Sandbox'): array
    {
        $id = (string) Str::uuid();
        $workspaceId = (string) Str::uuid();
        $now = now('UTC');
        DB::table('subscriptions.recurring_subscriptions')->insert([
            'id' => $id, 'workspace_id' => $workspaceId, 'plan_id' => 'c7ab1c54-33cf-5c00-9de2-5ae6d2c7da18', 'plan_price_id' => 'a5d9e095-bcee-54ef-8b40-f47256997d40',
            'provider' => 'fake', 'billing_environment' => $environment, 'provider_subscription_reference' => 'fake-subscription-'.$id,
            'status' => 'Active', 'current_period_start' => $now->copy()->subMonth(), 'current_period_end' => $now->copy()->addMonth(),
            'cancel_at_period_end' => false, 'canceled_at' => null, 'past_due_since' => null, 'last_provider_event_at' => $now->copy()->subDay(),
            'version' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return ['id' => $id, 'reference' => 'SUB-'.strtoupper(substr(hash_hmac('sha256', $workspaceId, (string) config('app.key')), 0, 10))];
    }

    private function enableActions(): void
    {
        config()->set('operations.backoffice.actions_enabled', true);
        config()->set('operations.backoffice.read_only', false);
    }
}

final class DriftedReconciliationGateway implements RecurringBillingReconciliationGateway
{
    public function inspect(Subscription $subscription): ProviderSubscriptionState
    {
        return new ProviderSubscriptionState(
            provider: $subscription->provider(), workspaceId: $subscription->workspaceId(), providerSubscriptionReference: $subscription->providerReference(), planPriceId: $subscription->planPriceId(),
            status: Subscription::STATUS_PAST_DUE, currentPeriodStart: $subscription->currentPeriodStart(), currentPeriodEnd: $subscription->currentPeriodEnd(),
            cancelAtPeriodEnd: false, canceledAt: null, observedAt: new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
    }
}
