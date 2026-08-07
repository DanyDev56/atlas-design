<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\RemoveMembershipHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class RemoveMembershipController extends Controller
{
    public function __construct(
        private readonly RemoveMembershipHandler $handler,
    ) {}

    public function __invoke(Request $request, string $workspaceId, string $membershipId): JsonResponse
    {
        $actorUserId = (string) $request->attributes->get('authenticated_user_id');
        $requestId = $request->header('Idempotency-Key') ?? (string) Str::uuid();

        try {
            $result = $this->handler->handle($actorUserId, $workspaceId, $membershipId, $requestId);
        } catch (\DomainException $exception) {
            $status = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }

        return response()->json($result);
    }
}
