<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\BetaCohortQueryHandler;
use Atlas\Modules\Operations\Domain\BetaCohortCatalog;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperatorBetaController extends Controller
{
    public function __construct(
        private readonly BetaCohortQueryHandler $cohort,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        try {
            $result = $this->cohort->overview();
        } catch (\Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
        $this->recordRead($request, 'operator.beta.metrics-read', OperatorPermissionCatalog::METRICS_READ_PRODUCT, 'BetaActivationMetrics');

        return response()->json($result);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['sometimes', 'string', Rule::in(['All', ...BetaCohortCatalog::STAGES])],
            'cell' => ['sometimes', 'string', Rule::in(['All', ...BetaCohortCatalog::PRICING_CELLS])],
            'status' => ['sometimes', 'string', Rule::in(['All', 'Active', 'Exited'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        try {
            $result = $this->cohort->page(
                (string) ($validated['stage'] ?? 'All'),
                (string) ($validated['cell'] ?? 'All'),
                (string) ($validated['status'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
        $this->recordRead($request, 'operator.beta.list-read', OperatorPermissionCatalog::BETA_READ, 'BetaParticipantList');

        return response()->json($result);
    }

    public function show(Request $request, string $betaCode): JsonResponse
    {
        try {
            $result = $this->cohort->diagnostic($betaCode);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->unavailable();
        }
        if ($result === null) {
            return response()->json(['error' => 'BetaParticipantNotFound', 'messages' => []], 404);
        }
        $this->recordRead($request, 'operator.beta.diagnostic-read', OperatorPermissionCatalog::BETA_READ, 'BetaParticipantDiagnostic', $betaCode);

        return response()->json($result);
    }

    private function recordRead(Request $request, string $action, string $permission, string $targetType, ?string $targetId = null): void
    {
        $userId = (string) $request->attributes->get('operator_user_id');
        $sessionId = (string) $request->attributes->get('operator_session_id');
        $correlationId = $request->attributes->get('correlation_id');
        $this->audit->record(
            action: $action,
            result: 'Allowed',
            operatorUserId: $userId,
            operatorSessionId: $sessionId,
            permission: $permission,
            targetType: $targetType,
            targetIdHash: $targetId !== null ? hash('sha256', strtoupper($targetId)) : null,
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'OperatorSourceUnavailable',
            'messages' => ['La projection de cohorte est momentanément indisponible.'],
        ], 503);
    }
}
