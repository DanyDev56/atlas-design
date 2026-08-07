<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Advisor;

use App\Http\Controllers\Controller;
use Atlas\Modules\Advisor\Application\AdvisorQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdvisorController extends Controller
{
    public function __construct(
        private readonly AdvisorQueryHandler $queries,
    ) {}

    public function overview(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getOverview(
            $this->actorId($request),
            $workspaceId,
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
