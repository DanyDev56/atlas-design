<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\AcceptWorkspaceInvitationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AcceptWorkspaceInvitationController extends Controller
{
    public function __construct(
        private readonly AcceptWorkspaceInvitationHandler $handler,
    ) {}

    public function __invoke(Request $request, string $invitationId): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'min:32'],
        ]);

        try {
            return response()->json($this->handler->handle(
                actorUserId: (string) $request->attributes->get('authenticated_user_id'),
                invitationId: $invitationId,
                token: $validated['token'],
                requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
                correlationId: $request->attributes->get('correlation_id'),
            ));
        } catch (\DomainException $exception) {
            $status = $exception->getMessage() === 'Idempotency conflict.' ? 409 : 422;

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }
    }
}
