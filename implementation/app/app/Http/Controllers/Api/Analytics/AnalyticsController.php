<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use Atlas\Modules\Analytics\Application\AnalyticsQueryHandler;
use Atlas\Modules\Analytics\Application\PublishAnalyticsSnapshotHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsQueryHandler $queries,
        private readonly PublishAnalyticsSnapshotHandler $publishSnapshot,
    ) {}

    public function latestSnapshot(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getLatestSnapshot(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function metric(Request $request, string $workspaceId, string $metricKey): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getMetric(
            $this->actorId($request),
            $workspaceId,
            $metricKey,
        ));
    }

    public function publishSnapshot(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->publishSnapshot->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
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
