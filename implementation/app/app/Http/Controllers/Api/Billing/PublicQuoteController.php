<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\GetPublicQuoteHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicQuoteController extends Controller
{
    public function __construct(
        private readonly GetPublicQuoteHandler $getPublicQuote,
    ) {}

    public function __invoke(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        $validated = $request->validate([
            'public_token' => ['required', 'string', 'min:32'],
        ]);

        try {
            return response()->json($this->getPublicQuote->handle(
                workspaceId: $workspaceId,
                quoteId: $quoteId,
                publicToken: $validated['public_token'],
            ));
        } catch (\DomainException) {
            return response()->json([
                'error' => 'Unauthenticated',
                'messages' => ['Ce lien est invalide ou a expiré.'],
            ], 401);
        }
    }
}
