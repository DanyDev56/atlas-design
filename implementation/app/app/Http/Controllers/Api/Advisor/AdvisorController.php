<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Advisor;

use App\Http\Controllers\Controller;
use Atlas\Modules\Advisor\Application\AdvisorQueryHandler;
use Atlas\Modules\Advisor\Application\RecommendationDecisionHandler;
use Atlas\Modules\Advisor\Domain\RecommendationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdvisorController extends Controller
{
    public function __construct(
        private readonly AdvisorQueryHandler $queries,
        private readonly RecommendationDecisionHandler $decisions,
    ) {}

    public function overview(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getOverview(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function complete(
        Request $request,
        string $workspaceId,
        string $recommendationId,
    ): JsonResponse {
        $validated = $request->validate([
            'completion_confirmation' => [
                'required',
                'string',
                'in:'.RecommendationPolicy::COMPLETION_CONFIRMATION,
            ],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->decisions->complete(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            recommendationId: $recommendationId,
            confirmation: $validated['completion_confirmation'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: (string) $request->header('Idempotency-Key', ''),
        ));
    }

    public function dismiss(
        Request $request,
        string $workspaceId,
        string $recommendationId,
    ): JsonResponse {
        $validated = $request->validate([
            'dismissal_reason' => [
                'required',
                'string',
                'in:'.implode(',', RecommendationPolicy::DISMISSAL_REASONS),
            ],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->decisions->dismiss(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            recommendationId: $recommendationId,
            reason: $validated['dismissal_reason'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: (string) $request->header('Idempotency-Key', ''),
        ));
    }

    private function actorId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            $code = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Recommendation not found.', 'Overview not found.' => 404,
                'Revision conflict.', 'Idempotency conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
