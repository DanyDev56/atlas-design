<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use Atlas\Modules\Billing\Application\BillingQueryHandler;
use Atlas\Modules\Billing\Application\CreateDepositInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateFinalInvoiceFromQuoteHandler;
use Atlas\Modules\Billing\Application\CreateQuoteHandler;
use Atlas\Modules\Billing\Application\SendQuoteHandler;
use Atlas\Modules\Billing\Application\UpdateQuoteDraftHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class QuoteController extends Controller
{
    public function __construct(
        private readonly CreateQuoteHandler $createQuote,
        private readonly UpdateQuoteDraftHandler $updateQuoteDraft,
        private readonly SendQuoteHandler $sendQuote,
        private readonly CreateFinalInvoiceFromQuoteHandler $createInvoiceFromQuote,
        private readonly CreateDepositInvoiceFromQuoteHandler $createDepositInvoiceFromQuote,
        private readonly BillingQueryHandler $queries,
    ) {}

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listQuotes(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function show(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getQuote(
            $this->actorId($request),
            $workspaceId,
            $quoteId,
        ));
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid'],
            'opportunity_id' => ['sometimes', 'nullable', 'uuid'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_cents' => ['required', 'integer', 'min:0'],
        ]);

        return $this->respond(fn () => $this->createQuote->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            clientId: $validated['client_id'],
            opportunityId: $validated['opportunity_id'] ?? null,
            lines: $validated['lines'],
            currency: $validated['currency'] ?? 'EUR',
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function update(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'integer', 'min:1'],
            'lines.*.unit_price_cents' => ['required', 'integer', 'min:0'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->updateQuoteDraft->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            quoteId: $quoteId,
            lines: $validated['lines'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
        ));
    }

    public function send(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->sendQuote->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            quoteId: $quoteId,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
        ));
    }

    public function createInvoice(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        return $this->respond(fn () => $this->createInvoiceFromQuote->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            quoteId: $quoteId,
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function createDepositInvoice(Request $request, string $workspaceId, string $quoteId): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->createDepositInvoiceFromQuote->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            quoteId: $quoteId,
            amountCents: (int) $validated['amount_cents'],
            expectedQuoteRevision: (int) $validated['expected_revision'],
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
