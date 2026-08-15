<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Atlas\Modules\Crm\Application\CreateOpportunityHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Atlas\Modules\Crm\Application\LoseOpportunityHandler;
use Atlas\Modules\Crm\Application\QualifyOpportunityHandler;
use Atlas\Modules\Crm\Application\UpdateOpportunityHandler;
use Atlas\Modules\Crm\Domain\Opportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class OpportunityController extends Controller
{
    public function __construct(
        private readonly CreateOpportunityHandler $createOpportunity,
        private readonly QualifyOpportunityHandler $qualifyOpportunity,
        private readonly UpdateOpportunityHandler $updateOpportunity,
        private readonly LoseOpportunityHandler $loseOpportunity,
        private readonly CrmQueryHandler $queries,
    ) {}

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listOpportunities(
            $this->actorId($request),
            $workspaceId,
            $request->query('status'),
        ));
    }

    public function show(Request $request, string $workspaceId, string $opportunityId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getOpportunity(
            $this->actorId($request),
            $workspaceId,
            $opportunityId,
        ));
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'uuid'],
            'contact_id' => ['sometimes', 'nullable', 'uuid'],
            'title' => ['required', 'string', 'min:2', 'max:200'],
            'estimated_amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
        ]);

        return $this->respond(fn () => $this->createOpportunity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            clientId: $validated['client_id'],
            contactId: $validated['contact_id'] ?? null,
            title: $validated['title'],
            estimatedAmountCents: $validated['estimated_amount_cents'] ?? null,
            currency: $validated['currency'] ?? 'EUR',
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function qualify(Request $request, string $workspaceId, string $opportunityId): JsonResponse
    {
        $validated = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->qualifyOpportunity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            opportunityId: $opportunityId,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function update(Request $request, string $workspaceId, string $opportunityId): JsonResponse
    {
        $validated = $request->validate([
            'changes' => ['required', 'array:contact_id,title,estimated_amount_cents,currency', 'min:1'],
            'changes.contact_id' => ['sometimes', 'nullable', 'uuid'],
            'changes.title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'changes.estimated_amount_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'changes.currency' => ['sometimes', 'string', 'size:3'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->updateOpportunity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            opportunityId: $opportunityId,
            changes: $validated['changes'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function lose(Request $request, string $workspaceId, string $opportunityId): JsonResponse
    {
        $validated = $request->validate([
            'loss_reason_code' => ['required', 'string', Rule::in(Opportunity::lossReasonCodes())],
            'loss_note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->loseOpportunity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            opportunityId: $opportunityId,
            lossReasonCode: $validated['loss_reason_code'],
            lossNote: $validated['loss_note'] ?? null,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function commercialContext(Request $request, string $workspaceId, string $opportunityId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getOpportunityCommercialContext(
            $this->actorId($request),
            $workspaceId,
            $opportunityId,
        ));
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
