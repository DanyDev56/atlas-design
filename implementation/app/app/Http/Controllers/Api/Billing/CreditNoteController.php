<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\Billing\Application\CreditNoteCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CreditNoteController extends Controller
{
    public function __construct(
        private readonly CreditNoteCommandHandler $commands,
        private readonly BillingQueryHandler $queries,
    ) {}

    public function index(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listCreditNotes(
            $this->actorId($request), $workspaceId, $invoiceId,
        ));
    }

    public function show(Request $request, string $workspaceId, string $creditNoteId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getCreditNote(
            $this->actorId($request), $workspaceId, $creditNoteId,
        ));
    }

    public function store(Request $request, string $workspaceId, string $invoiceId): JsonResponse
    {
        $validated = $this->validateDraft($request);
        return $this->respond(fn () => $this->commands->create(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            invoiceId: $invoiceId,
            lines: $validated['lines'],
            reason: $validated['reason'] ?? null,
            requestId: $this->requestId($request),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function update(Request $request, string $workspaceId, string $creditNoteId): JsonResponse
    {
        $validated = $this->validateDraft($request, true);
        return $this->respond(fn () => $this->commands->updateDraft(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            creditNoteId: $creditNoteId,
            lines: $validated['lines'],
            reason: $validated['reason'] ?? null,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $this->requestId($request),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function discard(Request $request, string $workspaceId, string $creditNoteId): JsonResponse
    {
        $validated = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        return $this->respond(fn () => $this->commands->discard(
            $this->actorId($request), $workspaceId, $creditNoteId,
            (int) $validated['expected_revision'], $this->requestId($request),
            $request->attributes->get('correlation_id'),
        ));
    }

    public function issue(Request $request, string $workspaceId, string $creditNoteId): JsonResponse
    {
        $validated = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        return $this->respond(fn () => $this->commands->issue(
            $this->actorId($request), $workspaceId, $creditNoteId,
            (int) $validated['expected_revision'], $this->requestId($request),
            $request->attributes->get('correlation_id'),
        ));
    }

    public function apply(Request $request, string $workspaceId, string $creditNoteId): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'remainder_disposition' => ['nullable', 'in:RefundDue,ClientCredit'],
            'expected_credit_note_revision' => ['required', 'integer', 'min:1'],
            'expected_invoice_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->commands->apply(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            creditNoteId: $creditNoteId,
            amountCents: (int) $validated['amount_cents'],
            remainderDisposition: $validated['remainder_disposition'] ?? null,
            expectedCreditNoteRevision: (int) $validated['expected_credit_note_revision'],
            expectedInvoiceRevision: (int) $validated['expected_invoice_revision'],
            requestId: $this->requestId($request),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    /** @return array<string, mixed> */
    private function validateDraft(Request $request, bool $revision = false): array
    {
        return $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'expected_revision' => [$revision ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
    }

    private function actorId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
    }

    private function requestId(Request $request): string
    {
        return $request->header('Idempotency-Key') ?? (string) Str::uuid();
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json($action(), $status);
        } catch (\DomainException $exception) {
            $code = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Invoice not found.', 'Credit note not found.' => 404,
                'Idempotency conflict.', 'Invoice version conflict.',
                'Credit note version or state conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $code);
        }
    }
}
