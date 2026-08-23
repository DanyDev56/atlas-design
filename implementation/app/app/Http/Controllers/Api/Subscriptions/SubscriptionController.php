<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use Atlas\Modules\Subscriptions\Application\CreateCheckoutSessionHandler;
use Atlas\Modules\Subscriptions\Application\SubscriptionOverviewQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionOverviewQueryHandler $overview,
        private readonly CreateCheckoutSessionHandler $checkout,
    ) {}

    public function show(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn (): array => $this->overview->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
        ));
    }

    public function checkout(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'billing_interval' => ['required', 'string', 'in:Monthly,Annual'],
        ]);
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '') {
            return response()->json([
                'error' => 'IdempotencyKeyRequired',
                'messages' => ['Idempotency-Key header is required.'],
            ], 422);
        }

        return $this->respond(fn (): array => $this->checkout->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            billingInterval: $validated['billing_interval'],
            idempotencyKey: $idempotencyKey,
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
            $status = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Checkout unavailable.' => 409,
                'Idempotency conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }
    }
}
