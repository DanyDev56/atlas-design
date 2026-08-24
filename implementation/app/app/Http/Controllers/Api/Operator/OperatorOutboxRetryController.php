<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\OutboxRetryActionException;
use Atlas\Modules\Operations\Application\RetryManagedOutboxMessageHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OperatorOutboxRetryController extends Controller
{
    public function __construct(
        private readonly RetryManagedOutboxMessageHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function preview(Request $request, string $eventId): JsonResponse
    {
        $validated = $this->validatedProposal($request);
        $context = $this->context($request, $eventId);

        try {
            $result = $this->handler->preview($eventId, (string) $validated['reason_code']);
        } catch (OutboxRetryActionException $exception) {
            $this->record($context, 'operator.outbox-retry.preview-denied', 'Denied', (string) $validated['reason_code'], ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        $this->record($context, 'operator.outbox-retry.previewed', 'Allowed', (string) $validated['reason_code'], [
            'event_type' => (string) $result['event_type'],
            'attempts' => (int) $result['current']['attempts'],
        ]);

        return response()->json($result);
    }

    public function update(Request $request, string $eventId): JsonResponse
    {
        $validated = [
            ...$this->validatedProposal($request),
            ...$request->validate(['preview_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']]),
        ];
        $idempotencyKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            return response()->json([
                'error' => 'OperatorIdempotencyKeyRequired',
                'messages' => ['Un en-tête Idempotency-Key valide est requis.'],
            ], 422);
        }

        $context = $this->context($request, $eventId);
        $reason = (string) $validated['reason_code'];
        $this->record($context, 'operator.outbox-retry.attempted', 'Attempted', $reason, []);

        try {
            $result = $this->handler->apply(
                $eventId,
                $context['user_id'],
                $context['session_id'],
                $reason,
                (string) $validated['preview_fingerprint'],
                $idempotencyKey,
                $context['correlation_id'],
            );
        } catch (OutboxRetryActionException $exception) {
            $this->record($context, 'operator.outbox-retry.denied', 'Denied', $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        if ((bool) $result['replayed']) {
            $this->record($context, 'operator.outbox-retry.replayed', 'Replayed', $reason, []);
        }

        return response()->json($result);
    }

    /** @return array{reason_code: string} */
    private function validatedProposal(Request $request): array
    {
        return $request->validate([
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/'],
        ]);
    }

    /** @return array{user_id: string, session_id: string, correlation_id: string|null, event_id: string} */
    private function context(Request $request, string $eventId): array
    {
        $correlationId = $request->attributes->get('correlation_id');

        return [
            'user_id' => (string) $request->attributes->get('operator_user_id'),
            'session_id' => (string) $request->attributes->get('operator_session_id'),
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'event_id' => strtolower($eventId),
        ];
    }

    /** @param array{user_id: string, session_id: string, correlation_id: string|null, event_id: string} $context @param array<string, scalar|null> $metadata */
    private function record(array $context, string $action, string $result, string $reason, array $metadata): void
    {
        $this->audit->record(
            action: $action,
            result: $result,
            operatorUserId: $context['user_id'],
            operatorSessionId: $context['session_id'],
            permission: OperatorPermissionCatalog::OUTBOX_RETRY,
            targetType: 'OutboxMessage',
            targetIdHash: hash('sha256', $context['event_id']),
            correlationId: $context['correlation_id'],
            reason: $reason,
            metadata: $metadata,
        );
    }

    private function error(OutboxRetryActionException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->errorCode,
            'messages' => [$exception->getMessage()],
        ], $exception->httpStatus);
    }
}
