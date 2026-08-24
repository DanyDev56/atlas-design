<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\ReconcileManagedSubscriptionHandler;
use Atlas\Modules\Operations\Application\SubscriptionReconciliationActionException;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorSubscriptionReconciliationController extends Controller
{
    public function __construct(
        private readonly ReconcileManagedSubscriptionHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function preview(Request $request, string $reference): JsonResponse
    {
        $validated = $this->proposal($request);
        $context = $this->context($request, $reference);
        try {
            $result = $this->handler->preview($reference, (int) $validated['expected_version'], (string) $validated['reason_code']);
        } catch (SubscriptionReconciliationActionException $exception) {
            $this->record($context, 'operator.subscription-reconciliation.preview-denied', 'Denied', (string) $validated['reason_code'], ['error' => $exception->errorCode]);
            return $this->error($exception);
        }
        $this->record($context, 'operator.subscription-reconciliation.previewed', 'Allowed', (string) $validated['reason_code'], ['aligned' => (bool) $result['aligned'], 'fields' => array_column($result['changes'], 'field')]);

        return response()->json($result);
    }

    public function update(Request $request, string $reference): JsonResponse
    {
        $validated = [...$this->proposal($request), ...$request->validate(['preview_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']])];
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 128) {
            return response()->json(['error' => 'OperatorIdempotencyKeyRequired', 'messages' => ['Un en-tête Idempotency-Key valide est requis.']], 422);
        }
        $context = $this->context($request, $reference);
        $reason = (string) $validated['reason_code'];
        $this->record($context, 'operator.subscription-reconciliation.attempted', 'Attempted', $reason, []);
        try {
            $result = $this->handler->apply($reference, (int) $validated['expected_version'], $reason, (string) $validated['preview_fingerprint'], $context['user_id'], $context['session_id'], $key, $context['correlation_id']);
        } catch (SubscriptionReconciliationActionException $exception) {
            $this->record($context, 'operator.subscription-reconciliation.denied', 'Denied', $reason, ['error' => $exception->errorCode]);
            return $this->error($exception);
        }
        if ((bool) $result['replayed']) {
            $this->record($context, 'operator.subscription-reconciliation.replayed', 'Replayed', $reason, []);
        }

        return response()->json($result);
    }

    /** @return array{expected_version:int,reason_code:string} */
    private function proposal(Request $request): array
    {
        return $request->validate(['expected_version' => ['required', 'integer', 'min:1'], 'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/']]);
    }

    /** @return array{user_id:string,session_id:string,correlation_id:string|null,reference:string} */
    private function context(Request $request, string $reference): array
    {
        $correlation = $request->attributes->get('correlation_id');
        return ['user_id' => (string) $request->attributes->get('operator_user_id'), 'session_id' => (string) $request->attributes->get('operator_session_id'), 'correlation_id' => is_string($correlation) ? $correlation : null, 'reference' => $reference];
    }

    /** @param array{user_id:string,session_id:string,correlation_id:string|null,reference:string} $context @param array<string,mixed> $metadata */
    private function record(array $context, string $action, string $result, string $reason, array $metadata): void
    {
        $this->audit->record(action: $action, result: $result, operatorUserId: $context['user_id'], operatorSessionId: $context['session_id'], permission: OperatorPermissionCatalog::SUBSCRIPTIONS_RECONCILE, targetType: 'Subscription', targetIdHash: hash('sha256', $context['reference']), correlationId: $context['correlation_id'], reason: $reason, metadata: $metadata);
    }

    private function error(SubscriptionReconciliationActionException $exception): JsonResponse
    {
        return response()->json(['error' => $exception->errorCode, 'messages' => [$exception->getMessage()]], $exception->httpStatus);
    }
}
