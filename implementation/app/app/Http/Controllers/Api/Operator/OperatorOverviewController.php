<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use Atlas\Modules\Operations\Application\OperationsOverviewQueryHandler;
use Atlas\Modules\Operations\Domain\OperatorPermissionCatalog;
use Atlas\Modules\Operations\Infrastructure\Persistence\PostgresOperatorAuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OperatorOverviewController extends Controller
{
    public function __construct(
        private readonly OperationsOverviewQueryHandler $overview,
        private readonly PostgresOperatorAuditRepository $audit,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $result = $this->overview->overview();
        $this->recordRead($request, 'operator.overview.read', OperatorPermissionCatalog::DASHBOARD_READ);

        return response()->json($result);
    }

    public function outbox(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Pending', 'Retrying', 'DeadLetter', 'Dispatched'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->outbox(
                (string) ($validated['status'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.outbox.list-read', OperatorPermissionCatalog::OUTBOX_READ);

        return response()->json($result);
    }

    public function emails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Pending', 'Retrying', 'Failed', 'Accepted', 'Cancelled'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->emails(
                (string) ($validated['status'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.email.list-read', OperatorPermissionCatalog::EMAIL_READ);

        return response()->json($result);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Active', 'PastDue', 'Canceled'])],
            'environment' => ['sometimes', 'string', Rule::in(['All', 'Sandbox', 'Live', 'Unknown'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->subscriptions(
                (string) ($validated['status'] ?? 'All'),
                (string) ($validated['environment'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.subscription.list-read', OperatorPermissionCatalog::SUBSCRIPTIONS_READ);

        return response()->json($result);
    }

    public function webhooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Received', 'Processing', 'Processed', 'Ignored', 'Deferred', 'Failed'])],
            'environment' => ['sometimes', 'string', Rule::in(['All', 'Sandbox', 'Live', 'Unknown'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->webhooks(
                (string) ($validated['status'] ?? 'All'),
                (string) ($validated['environment'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.subscription-webhook.list-read', OperatorPermissionCatalog::SUBSCRIPTIONS_READ);

        return response()->json($result);
    }

    public function runtime(Request $request): JsonResponse
    {
        try {
            $result = $this->overview->runtime();
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.runtime.read', OperatorPermissionCatalog::DASHBOARD_READ);

        return response()->json($result);
    }

    public function http(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'window' => ['sometimes', 'integer', Rule::in([5, 15, 60, 1440])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->http(
                (int) ($validated['window'] ?? 15),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.http-metrics.list-read', OperatorPermissionCatalog::DASHBOARD_READ);

        return response()->json($result);
    }

    public function alerts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'state' => ['sometimes', 'string', Rule::in(['All', 'Healthy', 'Firing'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->alerts(
                (string) ($validated['state'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.alerts.list-read', OperatorPermissionCatalog::DASHBOARD_READ);

        return response()->json($result);
    }

    public function support(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Open', 'Acknowledged', 'InProgress', 'WaitingRequester', 'Resolved', 'Closed'])],
            'severity' => ['sometimes', 'string', Rule::in(['All', 'P0', 'P1', 'P2', 'P3'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->support(
                (string) ($validated['status'] ?? 'All'),
                (string) ($validated['severity'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.support.list-read', OperatorPermissionCatalog::SUPPORT_READ);

        return response()->json($result);
    }

    public function dataRequests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['All', 'Received', 'IdentityPending', 'Qualified', 'InPreparation', 'AwaitingApproval', 'Delivered', 'Rejected', 'Closed'])],
            'type' => ['sometimes', 'string', Rule::in(['All', 'Access', 'Rectification', 'Erasure', 'Restriction', 'Objection', 'Portability'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->dataRequests(
                (string) ($validated['status'] ?? 'All'),
                (string) ($validated['type'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.data-request.list-read', OperatorPermissionCatalog::COMPLIANCE_READ);

        return response()->json($result);
    }

    public function compliance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->compliance(
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.compliance.list-read', OperatorPermissionCatalog::COMPLIANCE_READ);

        return response()->json($result);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', Rule::in(['All', 'Backup', 'RestoreCanary'])],
            'status' => ['sometimes', 'string', Rule::in(['All', 'Succeeded', 'Failed'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $result = $this->overview->maintenance(
                (string) ($validated['kind'] ?? 'All'),
                (string) ($validated['status'] ?? 'All'),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 20),
            );
        } catch (\Throwable) {
            return $this->sourceUnavailable();
        }

        $this->recordRead($request, 'operator.maintenance.list-read', OperatorPermissionCatalog::DASHBOARD_READ);

        return response()->json($result);
    }

    private function recordRead(Request $request, string $action, string $permission): void
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
            targetType: 'OperationsProjection',
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
    }

    private function sourceUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => 'OperatorSourceUnavailable',
            'messages' => ['La source opérateur est momentanément indisponible.'],
        ], 503);
    }
}
