<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\BillingDocumentArtifactService;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DocumentArtifactController extends Controller
{
    public function __construct(
        private readonly WorkspaceAuthorizer $authorizer,
        private readonly BillingDocumentArtifactService $documents,
    ) {}

    public function show(
        Request $request,
        string $workspaceId,
        string $documentType,
        string $documentId,
    ): Response|JsonResponse {
        $permissions = [
            'quote' => 'billing.quotes.read',
            'invoice' => 'billing.invoices.read',
            'credit_note' => 'billing.credit-notes.read',
        ];
        if (! isset($permissions[$documentType])) {
            return response()->json(['error' => 'NotFound', 'messages' => ['Document type not found.']], 404);
        }

        try {
            $this->authorizer->authorize(
                (string) $request->attributes->get('authenticated_user_id'),
                $workspaceId,
                $permissions[$documentType],
            );
            $artifact = $this->documents->get($workspaceId, $documentType, $documentId);

            return response($artifact['content'], 200, [
                'Content-Type' => $artifact['media_type'],
                'Content-Disposition' => 'attachment; filename="'.$artifact['filename'].'"',
                'Content-Length' => (string) strlen($artifact['content']),
                'ETag' => '"'.$artifact['content_hash'].'"',
                'Cache-Control' => 'private, immutable, max-age=31536000',
            ]);
        } catch (\DomainException $exception) {
            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $exception->getMessage() === 'Unauthorized.' ? 403 : 404);
        }
    }
}
