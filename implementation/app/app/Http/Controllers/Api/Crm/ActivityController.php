<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Atlas\Modules\Crm\Application\CorrectActivityHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Crm\Application\RecordActivityHandler;
use Atlas\Modules\Crm\Application\RemoveActivityHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ActivityController extends Controller
{
    public function __construct(
        private readonly RecordActivityHandler $recordActivity,
        private readonly CorrectActivityHandler $correctActivity,
        private readonly RemoveActivityHandler $removeActivity,
        private readonly CrmQueryHandler $queries,
    ) {}

    public function index(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listClientActivities(
            $this->actorId($request),
            $workspaceId,
            $clientId,
        ));
    }

    public function store(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['sometimes', 'nullable', 'uuid'],
            'opportunity_id' => ['sometimes', 'nullable', 'uuid'],
            'kind' => ['required', 'in:Note,Call,Meeting,Email'],
            'summary' => ['required', 'string', 'min:2', 'max:2000'],
            'occurred_at' => ['required', 'date'],
        ]);

        return $this->respond(fn () => $this->recordActivity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            clientId: $clientId,
            contactId: $validated['contact_id'] ?? null,
            opportunityId: $validated['opportunity_id'] ?? null,
            kind: $validated['kind'],
            summary: $validated['summary'],
            occurredAt: $validated['occurred_at'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function update(Request $request, string $workspaceId, string $activityId): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'array:kind,summary,occurred_at'],
            'content.kind' => ['required', 'in:Note,Call,Meeting,Email'],
            'content.summary' => ['required', 'string', 'min:2', 'max:2000'],
            'content.occurred_at' => ['required', 'date'],
            'correction_reason' => ['required', 'string', 'min:2', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->correctActivity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            activityId: $activityId,
            kind: $validated['content']['kind'],
            summary: $validated['content']['summary'],
            occurredAt: $validated['content']['occurred_at'],
            correctionReason: $validated['correction_reason'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function remove(Request $request, string $workspaceId, string $activityId): JsonResponse
    {
        $validated = $request->validate([
            'removal_reason' => ['required', 'string', 'min:2', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->removeActivity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            activityId: $activityId,
            removalReason: $validated['removal_reason'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
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
