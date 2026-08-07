<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\AcceptQuoteHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PublicQuoteAcceptController extends Controller
{
    public function __construct(
        private readonly AcceptQuoteHandler $acceptQuote,
    ) {}

    public function __invoke(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        $validated = $request->validate([
            'public_token' => ['required', 'string', 'min:32'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->acceptQuote->handle(
            workspaceId: $workspaceId,
            quoteId: $quoteId,
            publicToken: $validated['public_token'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            $code = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Invalid or expired proof.', 'Proof mismatch.' => 401,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
