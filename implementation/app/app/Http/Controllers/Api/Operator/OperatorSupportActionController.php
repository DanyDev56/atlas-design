<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\ManageSupportCaseHandler;
use Atlas\Modules\Operations\Application\SupportCaseActionException;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperatorSupportActionController extends Controller
{
    public function __construct(
        private readonly ManageSupportCaseHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function preview(Request $request, string $reference): JsonResponse
    {
        $validated = $this->validatedProposal($request);
        $context = $this->context($request, $reference);

        try {
            $result = $this->handler->preview(
                $reference,
                $context['user_id'],
                (int) $validated['expected_revision'],
                (string) $validated['status'],
                (string) $validated['assignment'],
                (string) $validated['reason_code'],
            );
        } catch (SupportCaseActionException $exception) {
            $this->record($context, 'operator.support-case.preview-denied', 'Denied', (string) $validated['reason_code'], ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        $this->record($context, 'operator.support-case.previewed', 'Allowed', (string) $validated['reason_code'], [
            'expected_revision' => (int) $validated['expected_revision'],
            'proposed_status' => (string) $validated['status'],
            'proposed_assignment' => (string) $validated['assignment'],
        ]);

        return response()->json($result);
    }

    public function update(Request $request, string $reference): JsonResponse
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
        $context = $this->context($request, $reference);
        $reason = (string) $validated['reason_code'];
        $this->record($context, 'operator.support-case.manage-attempted', 'Attempted', $reason, [
            'expected_revision' => (int) $validated['expected_revision'],
            'proposed_status' => (string) $validated['status'],
            'proposed_assignment' => (string) $validated['assignment'],
        ]);

        try {
            $result = $this->handler->apply(
                $reference,
                $context['user_id'],
                $context['session_id'],
                (int) $validated['expected_revision'],
                (string) $validated['status'],
                (string) $validated['assignment'],
                $reason,
                (string) $validated['preview_fingerprint'],
                $idempotencyKey,
                $context['correlation_id'],
            );
        } catch (SupportCaseActionException $exception) {
            $this->record($context, 'operator.support-case.manage-denied', 'Denied', $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        if ((bool) $result['replayed']) {
            $this->record($context, 'operator.support-case.manage-replayed', 'Replayed', $reason, ['revision' => (int) $result['revision']]);
        }

        return response()->json($result);
    }

    /** @return array{expected_revision: int, status: string, assignment: string, reason_code: string} */
    private function validatedProposal(Request $request): array
    {
        return $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::in(['Keep', 'Acknowledged', 'InProgress', 'WaitingRequester', 'Resolved', 'Closed'])],
            'assignment' => ['required', 'string', Rule::in(['Keep', 'Self', 'Unassigned'])],
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/'],
        ]);
    }

    /** @return array{user_id: string, session_id: string, correlation_id: string|null, reference: string} */
    private function context(Request $request, string $reference): array
    {
        $correlationId = $request->attributes->get('correlation_id');

        return [
            'user_id' => (string) $request->attributes->get('operator_user_id'),
            'session_id' => (string) $request->attributes->get('operator_session_id'),
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'reference' => strtoupper($reference),
        ];
    }

    /** @param array{user_id: string, session_id: string, correlation_id: string|null, reference: string} $context @param array<string, scalar|null> $metadata */
    private function record(array $context, string $action, string $result, string $reason, array $metadata): void
    {
        $this->audit->record(
            action: $action,
            result: $result,
            operatorUserId: $context['user_id'],
            operatorSessionId: $context['session_id'],
            permission: OperatorPermissionCatalog::SUPPORT_MANAGE,
            targetType: 'SupportCase',
            targetIdHash: hash('sha256', $context['reference']),
            correlationId: $context['correlation_id'],
            reason: $reason,
            metadata: $metadata,
        );
    }

    private function error(SupportCaseActionException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->errorCode,
            'messages' => [$exception->getMessage()],
        ], $exception->httpStatus);
    }
}
