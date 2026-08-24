<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\DataExportActionException;
use Atlas\Modules\Operations\Application\ManageApprovedDataExportHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OperatorDataExportController extends Controller
{
    public function __construct(
        private readonly ManageApprovedDataExportHandler $handler,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function requestPreview(Request $request, string $reference): JsonResponse
    {
        $reason = $this->reason($request);
        $context = $this->context($request);
        try {
            $result = $this->handler->previewRequest($reference, $context['user_id'], $reason);
        } catch (DataExportActionException $exception) {
            $this->record($context, 'operator.data-export.request-preview-denied', 'Denied', OperatorPermissionCatalog::EXPORTS_REQUEST, 'DataRequest', $reference, $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }
        $this->record($context, 'operator.data-export.request-previewed', 'Allowed', OperatorPermissionCatalog::EXPORTS_REQUEST, 'DataRequest', $reference, $reason);

        return response()->json($result);
    }

    public function requestExport(Request $request, string $reference): JsonResponse
    {
        $validated = [...$request->validate([
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/'],
            'preview_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ])];
        $key = $this->idempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }
        $context = $this->context($request);
        $reason = (string) $validated['reason_code'];
        $this->record($context, 'operator.data-export.request-attempted', 'Attempted', OperatorPermissionCatalog::EXPORTS_REQUEST, 'DataRequest', $reference, $reason);
        try {
            $result = $this->handler->request($reference, $context['user_id'], $context['session_id'], $reason, (string) $validated['preview_fingerprint'], $key, $context['correlation_id']);
        } catch (DataExportActionException $exception) {
            $this->record($context, 'operator.data-export.request-denied', 'Denied', OperatorPermissionCatalog::EXPORTS_REQUEST, 'DataRequest', $reference, $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        return response()->json($result, 201);
    }

    public function approvalPreview(Request $request, string $reference): JsonResponse
    {
        $reason = $this->reason($request);
        $context = $this->context($request);
        try {
            $result = $this->handler->previewApproval($reference, $context['user_id'], $reason);
        } catch (DataExportActionException $exception) {
            $this->record($context, 'operator.data-export.approval-preview-denied', 'Denied', OperatorPermissionCatalog::EXPORTS_APPROVE, 'DataExport', $reference, $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }
        $this->record($context, 'operator.data-export.approval-previewed', 'Allowed', OperatorPermissionCatalog::EXPORTS_APPROVE, 'DataExport', $reference, $reason);

        return response()->json($result);
    }

    public function approve(Request $request, string $reference): JsonResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/'],
            'preview_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ]);
        $key = $this->idempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }
        $context = $this->context($request);
        $reason = (string) $validated['reason_code'];
        $this->record($context, 'operator.data-export.approval-attempted', 'Attempted', OperatorPermissionCatalog::EXPORTS_APPROVE, 'DataExport', $reference, $reason);
        try {
            $result = $this->handler->approve($reference, $context['user_id'], $context['session_id'], $reason, (string) $validated['preview_fingerprint'], $key, $context['correlation_id']);
        } catch (DataExportActionException $exception) {
            $this->record($context, 'operator.data-export.approval-denied', 'Denied', OperatorPermissionCatalog::EXPORTS_APPROVE, 'DataExport', $reference, $reason, ['error' => $exception->errorCode]);

            return $this->error($exception);
        }

        return response()->json($result);
    }

    public function download(Request $request, string $reference): Response|JsonResponse
    {
        $reason = $this->reason($request);
        $key = $this->idempotencyKey($request);
        if ($key instanceof JsonResponse) {
            return $key;
        }
        $context = $this->context($request);
        $this->record($context, 'operator.data-export.download-attempted', 'Attempted', OperatorPermissionCatalog::EXPORTS_DOWNLOAD, 'DataExport', $reference, $reason);
        try {
            $result = $this->handler->download($reference, $context['user_id'], $context['session_id'], $reason, $key, $context['correlation_id']);
        } catch (DataExportActionException $exception) {
            if ($exception->errorCode !== 'DataExportExpired') {
                $this->record($context, 'operator.data-export.download-denied', 'Denied', OperatorPermissionCatalog::EXPORTS_DOWNLOAD, 'DataExport', $reference, $reason, ['error' => $exception->errorCode]);
            }

            return $this->error($exception);
        }

        return response($result['content'], 200, [
            'Content-Type' => $result['media_type'].'; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Atlas-Content-SHA256' => $result['content_fingerprint'],
            'X-Atlas-Idempotent-Replay' => $result['replayed'] ? 'true' : 'false',
        ]);
    }

    private function reason(Request $request): string
    {
        return (string) $request->validate(['reason_code' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9._-]{3,64}$/']])['reason_code'];
    }

    private function idempotencyKey(Request $request): string|JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '' || strlen($key) > 128) {
            return response()->json(['error' => 'OperatorIdempotencyKeyRequired', 'messages' => ['Un en-tête Idempotency-Key valide est requis.']], 422);
        }

        return $key;
    }

    /** @return array{user_id:string,session_id:string,correlation_id:string|null} */
    private function context(Request $request): array
    {
        $correlation = $request->attributes->get('correlation_id');

        return [
            'user_id' => (string) $request->attributes->get('operator_user_id'),
            'session_id' => (string) $request->attributes->get('operator_session_id'),
            'correlation_id' => is_string($correlation) ? $correlation : null,
        ];
    }

    /** @param array{user_id:string,session_id:string,correlation_id:string|null} $context @param array<string, scalar|null> $metadata */
    private function record(array $context, string $action, string $result, string $permission, string $targetType, string $reference, string $reason, array $metadata = []): void
    {
        $this->audit->record(
            action: $action, result: $result, operatorUserId: $context['user_id'], operatorSessionId: $context['session_id'],
            permission: $permission, targetType: $targetType, targetIdHash: hash('sha256', strtoupper($reference)),
            correlationId: $context['correlation_id'], reason: $reason, metadata: $metadata,
        );
    }

    private function error(DataExportActionException $exception): JsonResponse
    {
        return response()->json(['error' => $exception->errorCode, 'messages' => [$exception->getMessage()]], $exception->httpStatus);
    }
}
