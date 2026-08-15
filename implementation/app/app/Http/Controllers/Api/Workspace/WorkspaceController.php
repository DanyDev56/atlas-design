<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use Atlas\Modules\Workspace\Application\WorkspaceSummaryQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceSummaryQueryHandler $summary,
    ) {}

    public function summary(Request $request, string $workspaceId): JsonResponse
    {
        try {
            return response()->json($this->summary->handle(
                (string) $request->attributes->get('authenticated_user_id'),
                $workspaceId,
            ));
        } catch (\DomainException $exception) {
            $status = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Workspace not found.' => 404,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }
    }
}
