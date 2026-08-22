<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Atlas\Modules\Crm\Application\PreviewHistoricalClientsHandler;
use Atlas\Modules\Crm\Application\ConfirmHistoricalClientsImportHandler;
use Atlas\Modules\Crm\Infrastructure\Persistence\PostgresClientHistoryImportRunRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ClientHistoryImportController extends Controller
{
    public function __construct(
        private readonly PreviewHistoricalClientsHandler $previewHistoricalClients,
        private readonly ConfirmHistoricalClientsImportHandler $confirmHistoricalClientsImport,
        private readonly PostgresClientHistoryImportRunRepository $importRuns,
        private readonly OutboxProcessor $outbox,
    ) {}

    public function preview(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'source_system' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9._-]+$/'],
            'source_exported_at' => ['required', 'date', 'before_or_equal:now'],
            'file' => ['required', 'file', 'max:256', 'mimes:csv,txt'],
        ]);

        return $this->respond(fn() => $this->previewHistoricalClients->handle(
            actorUserId: (string) $request->attributes->get('authenticated_user_id'),
            workspaceId: $workspaceId,
            sourceSystem: $validated['source_system'],
            sourceExportedAt: $validated['source_exported_at'],
            contents: $validated['file']->get(),
        ), 201);
    }

    public function confirm(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'preview_id' => ['required', 'string', 'uuid'],
            'package_hash' => ['required', 'string', 'regex:/^(sha256:)?[a-f0-9]{64}$/i'],
            'source_system' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9._-]+$/'],
            'source_exported_at' => ['required', 'date', 'before_or_equal:now'],
        ]);

        $packageHash = preg_replace('/^sha256:/i', '', $validated['package_hash']);

        return $this->respond(function () use ($request, $workspaceId, $validated, $packageHash): array {
            $accepted = $this->confirmHistoricalClientsImport->handle(
                actorUserId: (string) $request->attributes->get('authenticated_user_id'),
                workspaceId: $workspaceId,
                previewId: $validated['preview_id'],
                packageHash: $packageHash,
                sourceSystem: $validated['source_system'],
                sourceExportedAt: $validated['source_exported_at'],
                requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
                correlationId: $request->attributes->get('correlation_id'),
            );

            $this->outbox->processPending(100);
            $run = $this->importRuns->findById($workspaceId, $accepted['import_run_id']);

            if ($run === null) {
                return $accepted;
            }

            return [
                ...$accepted,
                'status' => $run['status'] ?? $accepted['status'],
                'processed_count' => (int) ($run['processed_count'] ?? $accepted['processed_count']),
                'client_count' => (int) ($run['client_count'] ?? $accepted['client_count']),
            ];
        }, 202);
    }

    public function show(Request $request, string $workspaceId, string $importRunId): JsonResponse
    {
        return $this->respond(function () use ($workspaceId, $importRunId): array {
            $run = $this->importRuns->findById($workspaceId, $importRunId);

            if ($run === null) {
                return [
                    'import_run_id' => $importRunId,
                    'status' => 'NotFound',
                    'processed_count' => 0,
                    'client_count' => 0,
                ];
            }

            return [
                'import_run_id' => $importRunId,
                'status' => $run['status'] ?? 'Processing',
                'processed_count' => (int) ($run['processed_count'] ?? 0),
                'client_count' => (int) ($run['client_count'] ?? 0),
            ];
        });
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            $code = $exception->getMessage() === 'Unauthorized.' ? 403 : 422;

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
