<?php

declare(strict_types=1);

namespace Tests\Integration\Demo;

use Atlas\Composition\Demo\DemoAccountSeeder;
use Atlas\Modules\Crm\Application\CorrectActivityHandler;
use Atlas\Modules\Crm\Application\RemoveActivityHandler;
use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;

final class DemoAccountSeederTest extends IntegrationTestCase
{
    public function test_seeds_a_complete_and_idempotent_ui_test_scenario(): void
    {
        $result = app(DemoAccountSeeder::class)->seed();

        $this->assertSame(DemoAccountSeeder::EMAIL, $result->email);
        $this->assertTrue($result->sampleDataSeeded);

        $this->assertSame(6, $result->resourceCounts['clients']);
        $this->assertSame(7, $result->resourceCounts['contacts']);
        $this->assertSame(7, $result->resourceCounts['opportunities']);
        $this->assertSame(8, $result->resourceCounts['activities']);
        $this->assertSame(6, $result->resourceCounts['quotes']);
        $this->assertSame(3, $result->resourceCounts['invoices']);

        $this->assertSame(1, DB::table('crm.opportunities')
            ->where('workspace_id', $result->workspaceId)->where('status', 'Open')->count());
        $this->assertSame(1, DB::table('billing.quotes')
            ->where('workspace_id', $result->workspaceId)->where('status', 'Draft')->count());
        $this->assertSame(1, DB::table('billing.quotes')
            ->where('workspace_id', $result->workspaceId)->where('status', 'Sent')->count());
        $this->assertSame(4, DB::table('billing.quotes')
            ->where('workspace_id', $result->workspaceId)->where('status', 'Accepted')->count());

        $this->assertSame(1, DB::table('billing.invoices')
            ->where('workspace_id', $result->workspaceId)->where('status', 'Draft')->count());
        $this->assertTrue(DB::table('billing.invoices')
            ->where('workspace_id', $result->workspaceId)
            ->where('settlement_status', 'PartiallyPaid')
            ->where('due_date', '<', now())
            ->where('balance_cents', 240000)
            ->exists());
        $this->assertTrue(DB::table('billing.invoices')
            ->where('workspace_id', $result->workspaceId)
            ->where('settlement_status', 'Paid')
            ->exists());

        $this->assertTrue(DB::table('analytics.snapshots')->where('workspace_id', $result->workspaceId)->exists());
        $this->assertTrue(DB::table('business_health.current_assessments')->where('workspace_id', $result->workspaceId)->exists());
        $this->assertTrue(DB::table('advisor.overviews')->where('workspace_id', $result->workspaceId)->exists());
        $this->assertGreaterThanOrEqual(1, $result->resourceCounts['active_recommendations']);
        $this->assertGreaterThanOrEqual(1, $result->resourceCounts['unread_notifications']);
        $this->assertSame(6, DB::table('crm.clients')
            ->where('workspace_id', $result->workspaceId)
            ->whereNotNull('primary_contact_id')
            ->count());
        $this->assertSame(7, DB::table('crm.activities')
            ->where('workspace_id', $result->workspaceId)
            ->where('status', 'Recorded')
            ->count());
        $this->assertSame(1, DB::table('crm.activities')
            ->where('workspace_id', $result->workspaceId)
            ->where('status', 'Removed')
            ->count());
        $this->assertSame(1, DB::table('crm.activity_revisions')
            ->where('workspace_id', $result->workspaceId)
            ->count());

        $activity = DB::table('crm.activities')
            ->where('workspace_id', $result->workspaceId)
            ->where('status', 'Recorded')
            ->where('version', 1)
            ->orderBy('created_at')
            ->first();
        app(CorrectActivityHandler::class)->handle(
            actorUserId: $result->userId,
            workspaceId: $result->workspaceId,
            activityId: $activity->id,
            kind: $activity->kind,
            summary: $activity->summary.' Correction démo.',
            occurredAt: $activity->occurred_at,
            correctionReason: 'Vérification de l’idempotence du scénario.',
            expectedRevision: 1,
            requestId: 'demo-test:correct-activity',
        );
        app(RemoveActivityHandler::class)->handle(
            actorUserId: $result->userId,
            workspaceId: $result->workspaceId,
            activityId: $activity->id,
            removalReason: 'Vérification du retrait terminal dans le scénario.',
            expectedRevision: 2,
            requestId: 'demo-test:remove-activity',
        );

        $second = app(DemoAccountSeeder::class)->seed();
        $this->assertFalse($second->sampleDataSeeded);
        $this->assertSame($result->resourceCounts, $second->resourceCounts);
        $this->assertSame(6, DB::table('crm.activities')
            ->where('workspace_id', $result->workspaceId)
            ->where('status', 'Recorded')
            ->count());
    }

    public function test_upgrades_the_previous_two_client_scenario_without_deleting_it(): void
    {
        $initial = app(DemoAccountSeeder::class)->seed();
        $enhancedNames = ['Maison Lumen', 'Nova Conseil', 'Cabinet Rivoli', 'Collectif Cobalt'];
        $enhancedClientIds = DB::table('crm.clients')
            ->where('workspace_id', $initial->workspaceId)
            ->whereIn('display_name', $enhancedNames)
            ->pluck('id')
            ->all();
        $enhancedQuoteIds = DB::table('billing.quotes')
            ->where('workspace_id', $initial->workspaceId)
            ->whereIn('client_id', $enhancedClientIds)
            ->pluck('id')
            ->all();
        $enhancedInvoiceIds = DB::table('billing.invoices')
            ->where('workspace_id', $initial->workspaceId)
            ->whereIn('client_id', $enhancedClientIds)
            ->pluck('id')
            ->all();

        DB::table('crm.contacts')->whereIn('client_id', $enhancedClientIds)->delete();
        DB::table('crm.activities')->whereIn('client_id', $enhancedClientIds)->delete();
        DB::table('billing.payments')->whereIn('invoice_id', $enhancedInvoiceIds)->delete();
        DB::table('billing.invoices')->whereIn('id', $enhancedInvoiceIds)->delete();
        DB::table('billing.public_document_proofs')->whereIn('document_id', $enhancedQuoteIds)->delete();
        DB::table('billing.quotes')->whereIn('id', $enhancedQuoteIds)->delete();
        DB::table('crm.opportunities')->whereIn('client_id', $enhancedClientIds)->delete();
        DB::table('crm.clients')->whereIn('id', $enhancedClientIds)->delete();

        foreach (['crm', 'billing', 'analytics'] as $schema) {
            DB::table($schema.'.idempotency_keys')
                ->where('key', 'like', 'demo-seed:v'.DemoAccountSeeder::SCENARIO_VERSION.':%')
                ->delete();
        }

        $upgraded = app(DemoAccountSeeder::class)->seed();

        $this->assertTrue($upgraded->sampleDataSeeded);
        $this->assertSame(6, $upgraded->resourceCounts['clients']);
        $this->assertSame(7, $upgraded->resourceCounts['contacts']);
        $this->assertSame(8, $upgraded->resourceCounts['activities']);
        $this->assertSame(6, $upgraded->resourceCounts['quotes']);
        $this->assertSame(3, $upgraded->resourceCounts['invoices']);
        $this->assertSame(1, DB::table('crm.clients')
            ->where('workspace_id', $initial->workspaceId)
            ->where('display_name', 'Les Ateliers du Marais')
            ->count());
        $this->assertSame(1, DB::table('crm.clients')
            ->where('workspace_id', $initial->workspaceId)
            ->where('display_name', 'Horizon Digital')
            ->count());
    }

    public function test_seeds_a_distinct_and_idempotent_empty_ui_scenario(): void
    {
        $result = app(DemoAccountSeeder::class)->seedEmpty();

        $this->assertSame(DemoAccountSeeder::EMPTY_EMAIL, $result->email);
        $this->assertSame(DemoAccountSeeder::EMPTY_PASSWORD, $result->password);
        $this->assertTrue($result->userCreated);
        $this->assertFalse($result->sampleDataSeeded);
        $this->assertSame([
            'clients' => 0,
            'contacts' => 0,
            'opportunities' => 0,
            'activities' => 0,
            'quotes' => 0,
            'invoices' => 0,
            'active_recommendations' => 0,
            'unread_notifications' => 0,
        ], $result->resourceCounts);
        $this->assertFalse(DB::table('analytics.snapshots')->where('workspace_id', $result->workspaceId)->exists());
        $this->assertFalse(DB::table('business_health.current_assessments')->where('workspace_id', $result->workspaceId)->exists());
        $this->assertFalse(DB::table('advisor.overviews')->where('workspace_id', $result->workspaceId)->exists());

        $second = app(DemoAccountSeeder::class)->seedEmpty();

        $this->assertFalse($second->userCreated);
        $this->assertSame($result->workspaceId, $second->workspaceId);
        $this->assertSame($result->resourceCounts, $second->resourceCounts);
    }
}
