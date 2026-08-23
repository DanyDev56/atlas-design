<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Subscriptions;

use App\Http\Controllers\Controller;
use Atlas\Modules\Subscriptions\Application\ProcessRecurringBillingWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RecurringBillingWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $provider,
        ProcessRecurringBillingWebhookHandler $handler,
    ): JsonResponse {
        if (! config('subscriptions.webhooks_enabled', false)) {
            return response()->json(['error' => 'NotFound', 'messages' => ['Not found.']], 404);
        }

        try {
            $signatureHeader = $provider === 'stripe' ? 'Stripe-Signature' : 'X-Atlas-Signature';
            $result = $handler->handle(
                provider: $provider,
                payload: $request->getContent(),
                signature: trim((string) $request->header($signatureHeader, '')),
            );

            return response()->json($result, $result['duplicate'] ? 200 : 202);
        } catch (\DomainException $exception) {
            $status = match ($exception->getMessage()) {
                'Invalid webhook signature.' => 401,
                'Webhook provider unavailable.' => 404,
                'Webhook verifier unavailable.' => 503,
                'Stripe billing unavailable.' => 503,
                'Webhook event id conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => 'WebhookRejected',
                'messages' => [$exception->getMessage()],
            ], $status);
        }
    }
}
