<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Billing;

use Illuminate\Support\Facades\DB;
use Tests\Integration\IntegrationTestCase;
use Tests\Support\AuthenticatesWorkspaceOwner;
use Tests\Support\RunsMvpCommercialFlow;

final class DocumentArtifactTest extends IntegrationTestCase
{
    use AuthenticatesWorkspaceOwner;
    use RunsMvpCommercialFlow;

    public function test_issued_quote_and_invoice_have_downloadable_pdf_artifacts(): void
    {
        $owner = $this->onboardOwner($this, 'documents@test.local');
        $flow = $this->runMvpJ2ThroughPayment($this, $owner);

        $this->assertSame(2, DB::table('billing.document_artifacts')
            ->where('workspace_id', $owner['workspace_id'])
            ->count());

        foreach ([
            ['quote', $flow['quote_id']],
            ['invoice', $flow['invoice_id']],
        ] as [$type, $id]) {
            $response = $this->get(
                "/api/workspaces/{$owner['workspace_id']}/documents/{$type}/{$id}/artifact",
                ['Authorization' => 'Bearer '.$owner['token']],
            )->assertOk()
                ->assertHeader('content-type', 'application/pdf')
                ->assertHeader('content-disposition');

            $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        }
    }

    public function test_document_artifact_is_workspace_isolated(): void
    {
        $owner = $this->onboardOwner($this, 'documents-owner@test.local');
        $other = $this->onboardOwner($this, 'documents-other@test.local');
        $flow = $this->runMvpJ2ThroughPayment($this, $owner);

        $this->get(
            "/api/workspaces/{$owner['workspace_id']}/documents/invoice/{$flow['invoice_id']}/artifact",
            ['Authorization' => 'Bearer '.$other['token']],
        )->assertForbidden();
    }

    public function test_historical_documents_without_generated_artifact_return_not_found(): void
    {
        $owner = $this->onboardOwner($this, 'documents-missing@test.local');

        $this->get(
            "/api/workspaces/{$owner['workspace_id']}/documents/invoice/00000000-0000-0000-0000-000000000000/artifact",
            ['Authorization' => 'Bearer '.$owner['token']],
        )->assertNotFound()
            ->assertJsonPath('messages.0', 'Document artifact not found.');
    }
}
