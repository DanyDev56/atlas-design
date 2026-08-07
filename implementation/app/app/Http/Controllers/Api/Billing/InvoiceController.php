<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\Billing\Application\IssueInvoiceHandler;
use Atlas\Modules\Billing\Application\RecordPaymentHandler;
use Atlas\Modules\Billing\Application\SendInvoiceHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly IssueInvoiceHandler $issueInvoice,
        private readonly SendInvoiceHandler $sendInvoice,
        private readonly RecordPaymentHandler $recordPayment,
        private readonly BillingQueryHandler $queries,
    ) {}

    public function show(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getInvoice(
            $this->actorId($request),
            $workspaceId,
            $invoiceId,
        ));
    }

    public function issue(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->issueInvoice->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            invoiceId: $invoiceId,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function send(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->sendInvoice->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            invoiceId: $invoiceId,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
        ));
    }

    public function recordPayment(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        return $this->respond(fn () => $this->recordPayment->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            invoiceId: $invoiceId,
            amountCents: (int) $validated['amount_cents'],
            reference: $validated['reference'] ?? null,
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    private function actorId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
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
