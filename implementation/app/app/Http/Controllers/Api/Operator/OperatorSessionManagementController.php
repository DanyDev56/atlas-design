<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\OperatorSessionActionException;
use Atlas\Modules\Operations\Application\RevokeManagedOperatorSessionHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorSessionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperatorSessionManagementController extends Controller
{
    public function __construct(
        private readonly PostgresOperatorSessionRegistry $registry,
        private readonly RevokeManagedOperatorSessionHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Active', 'Expired', 'Revoked'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $context = $this->context($request, null);

        try {
            $result = $this->registry->page(
                (string) ($validated['status'] ?? 'Active'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
                $context['session_id'],
                new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            );
        } catch (\Throwable) {
            return response()->json([
                'error' => 'OperatorSourceUnavailable',
                'messages' => ['Le registre des sessions opérateur est indisponible.'],
            ], 503);
        }

        $this->record($context, 'operator.session-registry.read', 'Allowed', OperatorPermissionCatalog::SESSIONS_READ);

        return response()->json($result);
    }

    public function preview(Request $request, string $reference): JsonResponse
    {
        $validated = $this->validatedProposal($request);
        $context = $this->context($request, $reference);

        try {
            $result = $this->handler->preview(
                $reference,
                $context['session_id'],
                (int) $validated['expected_revision'],
                (string) $validated['reason_code'],
            );
        } catch (OperatorSessionActionException $exception) {
            $this->record($context, 'operator.session-revoke.preview-denied', 'Denied', OperatorPermissionCatalog::SESSIONS_REVOKE, (string) $validated['reason_code'], ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        $this->record($context, 'operator.session-revoke.previewed', 'Allowed', OperatorPermissionCatalog::SESSIONS_REVOKE, (string) $validated['reason_code'], ['expected_revision' => (int) $validated['expected_revision']]);

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
        $this->record($context, 'operator.session-revoke.attempted', 'Attempted', OperatorPermissionCatalog::SESSIONS_REVOKE, $reason, ['expected_revision' => (int) $validated['expected_revision']]);

        try {
            $result = $this->handler->apply(
                $reference,
                $context['user_id'],
                $context['session_id'],
                (int) $validated['expected_revision'],
                $reason,
                (string) $validated['preview_fingerprint'],
                $idempotencyKey,
                $context['correlation_id'],
            );
        } catch (OperatorSessionActionException $exception) {
            $this->record($context, 'operator.session-revoke.denied', 'Denied', OperatorPermissionCatalog::SESSIONS_REVOKE, $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        if ((bool) $result['replayed']) {
            $this->record($context, 'operator.session-revoke.replayed', 'Replayed', OperatorPermissionCatalog::SESSIONS_REVOKE, $reason, ['revision' => (int) $result['revision']]);
        }

        return response()->json($result);
    }

    /** @return array{expected_revision: int, reason_code: string} */
    private function validatedProposal(Request $request): array
    {
        return $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/'],
        ]);
    }

    /** @return array{user_id: string, session_id: string, correlation_id: string|null, reference: string|null} */
    private function context(Request $request, ?string $reference): array
    {
        $correlationId = $request->attributes->get('correlation_id');

        return [
            'user_id' => (string) $request->attributes->get('operator_user_id'),
            'session_id' => (string) $request->attributes->get('operator_session_id'),
            'correlation_id' => is_string($correlationId) ? $correlationId : null,
            'reference' => $reference !== null ? strtoupper($reference) : null,
        ];
    }

    /** @param array{user_id: string, session_id: string, correlation_id: string|null, reference: string|null} $context @param array<string, scalar|null> $metadata */
    private function record(array $context, string $action, string $result, string $permission, ?string $reason = null, array $metadata = []): void
    {
        $this->audit->record(
            action: $action,
            result: $result,
            operatorUserId: $context['user_id'],
            operatorSessionId: $context['session_id'],
            permission: $permission,
            targetType: $context['reference'] !== null ? 'OperatorSession' : 'OperatorSessionRegistry',
            targetIdHash: $context['reference'] !== null ? hash('sha256', $context['reference']) : null,
            correlationId: $context['correlation_id'],
            reason: $reason,
            metadata: $metadata,
        );
    }

    private function error(OperatorSessionActionException $exception): JsonResponse
    {
        return response()->json([
            'error' => $exception->errorCode,
            'messages' => [$exception->getMessage()],
        ], $exception->httpStatus);
    }
}
