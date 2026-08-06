<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Workspace\Application\CreateWorkspaceHandler;
use Atlas\Modules\Workspace\Contracts\CreateWorkspaceCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SpikeCreateWorkspaceController extends Controller
{
    public function __construct(
        private readonly CreateWorkspaceHandler $handler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if ($request->keys() !== ['name']) {
            return response()->json([
                'error' => 'ValidationError',
                'messages' => ['Only the name field is allowed.'],
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);

        $result = $this->handler->handle(new CreateWorkspaceCommand(
            name: $validated['name'],
            requestedByUserId: '00000000-0000-4000-8000-000000000001',
            correlationId: $request->attributes->get('correlation_id'),
        ));

        return response()->json([
            'workspace_id' => $result->workspaceId,
            'status' => $result->status,
            'access_state' => $result->accessState,
            'event_id' => $result->eventId,
        ], 201);
    }
}
