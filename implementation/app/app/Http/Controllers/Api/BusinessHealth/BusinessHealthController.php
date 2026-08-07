<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\BusinessHealth;

use App\Http\Controllers\Controller;
use Atlas\Modules\BusinessHealth\Application\BusinessHealthQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BusinessHealthController extends Controller
{
    public function __construct(
        private readonly BusinessHealthQueryHandler $queries,
    ) {}

    public function current(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getCurrent(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function show(Request $request, string $workspaceId, string $assessmentId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getAssessment(
            $this->actorId($request),
            $workspaceId,
            $assessmentId,
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
            $code = $exception->getMessage() === 'Unauthorized.' ? 403 : 422;

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
