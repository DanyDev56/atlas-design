<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\ConfirmHistoricalBillingHistoryImportHandler;
use Atlas\Modules\Billing\Application\PreviewHistoricalBillingHistoryHandler;
use Atlas\Modules\Billing\Infrastructure\Persistence\PostgresBillingHistoryImportRunRepository;
use Atlas\Platform\Messaging\Infrastructure\OutboxProcessor;
use Atlas\Platform\Security\StepUpRequiredException;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BillingHistoryImportController extends Controller
{
    public function __construct(
        private readonly PreviewHistoricalBillingHistoryHandler $previewImport,
        private readonly ConfirmHistoricalBillingHistoryImportHandler $confirmImport,
        private readonly PostgresBillingHistoryImportRunRepository $runs,
        private readonly OutboxProcessor $outbox,
    ) {}

    public function preview(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'source_system' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9._-]+$/'],
            'source_exported_at' => ['required', 'date', 'before_or_equal:now'],
            'quotes_file' => ['required', 'file', 'max:512', 'mimes:csv,txt'],
            'invoices_file' => ['required', 'file', 'max:512', 'mimes:csv,txt'],
            'payments_file' => ['required', 'file', 'max:512', 'mimes:csv,txt'],
            'credit_notes_file' => ['required', 'file', 'max:512', 'mimes:csv,txt'],
        ]);

        return $this->respond(fn (): array => $this->previewImport->handle(
            actorUserId: (string) $request->attributes->get('authenticated_user_id'),
            workspaceId: $workspaceId,
            sourceSystem: $validated['source_system'],
            sourceExportedAt: $validated['source_exported_at'],
            quotesContents: $validated['quotes_file']->get(),
            invoicesContents: $validated['invoices_file']->get(),
            paymentsContents: $validated['payments_file']->get(),
            creditNotesContents: $validated['credit_notes_file']->get(),
        ), 201);
    }

    public function confirm(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'preview_id' => ['required', 'uuid'],
            'package_hash' => ['required', 'string', 'regex:/^(sha256:)?[a-f0-9]{64}$/i'],
            'source_system' => ['nullable', 'string', 'min:2', 'max:64', 'regex:/^[A-Za-z][A-Za-z0-9._-]+$/'],
            'source_exported_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        return $this->respond(function () use ($request, $workspaceId, $validated): array {
            $accepted = $this->confirmImport->handle(
                actorUserId: (string) $request->attributes->get('authenticated_user_id'),
                workspaceId: $workspaceId,
                sessionId: (string) $request->attributes->get('session_id'),
                previewId: $validated['preview_id'],
                packageHash: $validated['package_hash'],
                sourceSystem: $validated['source_system'] ?? '',
                sourceExportedAt: $validated['source_exported_at'] ?? '',
                requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
                correlationId: $request->attributes->get('correlation_id'),
            );
            $this->outbox->processPending(100);
            $run = $this->runs->findById($workspaceId, $accepted['import_run_id']);

            return $run === null ? $accepted : $this->serialize($run);
        }, 202);
    }

    public function show(Request $request, string $workspaceId, string $importRunId): JsonResponse
    {
        return $this->respond(function () use ($request, $workspaceId, $importRunId): array {
            app(WorkspaceAuthorizer::class)->authorize(
                (string) $request->attributes->get('authenticated_user_id'),
                $workspaceId,
                'billing.history.import',
            );
            $run = $this->runs->findById($workspaceId, $importRunId);
            if ($run === null) {
                throw new \DomainException('Import run not found.');
            }

            return $this->serialize($run);
        });
    }

    /** @param array<string, mixed> $run */
    private function serialize(array $run): array
    {
        return [
            'import_run_id' => $run['id'],
            'status' => $run['status'],
            'checkpoint' => $run['checkpoint'],
            'quote_count' => (int) $run['quote_count'],
            'invoice_count' => (int) $run['invoice_count'],
            'payment_count' => (int) $run['payment_count'],
            'credit_note_count' => (int) ($run['credit_note_count'] ?? 0),
            'processed_quotes' => (int) $run['processed_quotes'],
            'processed_invoices' => (int) $run['processed_invoices'],
            'processed_payments' => (int) $run['processed_payments'],
            'processed_credit_notes' => (int) ($run['processed_credit_notes'] ?? 0),
        ];
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            if ($exception instanceof StepUpRequiredException) {
                return response()->json([
                    'error' => 'StepUpRequired',
                    'messages' => [$exception->getMessage()],
                ], 403);
            }

            $message = $exception->getMessage();
            $status = match (true) {
                $message === 'Unauthorized.' => 403,
                $message === 'Conflict.', $message === 'Idempotency conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$message],
            ], $status);
        }
    }
}
