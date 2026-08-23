<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\AcceptWorkspaceInvitationHandler;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionAccessRestrictedException;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionLimitExceededException;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionPolicyUnavailableException;
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
            $status = match (true) {
                $exception instanceof SubscriptionAccessRestrictedException => 402,
                $exception instanceof SubscriptionPolicyUnavailableException => 503,
                $exception instanceof SubscriptionLimitExceededException => 409,
                $exception->getMessage() === 'Idempotency conflict.' => 409,
                default => 422,
            };

            $payload = [
                'error' => match (true) {
                    $exception instanceof SubscriptionAccessRestrictedException => 'SubscriptionAccessRestricted',
                    $exception instanceof SubscriptionPolicyUnavailableException => 'SubscriptionPolicyUnavailable',
                    $exception instanceof SubscriptionLimitExceededException => 'SubscriptionLimitExceeded',
                    default => class_basename($exception),
                },
                'messages' => [$exception->getMessage()],
            ];

            if ($exception instanceof SubscriptionLimitExceededException) {
                $payload['limit_name'] = $exception->limitName;
                $payload['limit'] = $exception->limit;
                $payload['current'] = $exception->current;
            }

            return response()->json($payload, $status);
        }
    }
}
