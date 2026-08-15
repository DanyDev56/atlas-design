<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use Atlas\Modules\Crm\Application\AddContactHandler;
use Atlas\Modules\Crm\Application\ChangeClientPrimaryContactHandler;
use Atlas\Modules\Crm\Application\CreateClientHandler;
use Atlas\Modules\Crm\Application\CrmQueryHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ClientController extends Controller
{
    public function __construct(
        private readonly CreateClientHandler $createClient,
        private readonly AddContactHandler $addContact,
        private readonly ChangeClientPrimaryContactHandler $changePrimaryContact,
        private readonly CrmQueryHandler $queries,
    ) {}

    public function index(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listClients(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function show(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getClient(
            $this->actorId($request),
            $workspaceId,
            $clientId,
        ));
    }

    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['required', 'in:Individual,Organization'],
            'display_name' => ['required', 'string', 'min:2', 'max:160'],
            'profile' => ['sometimes', 'array'],
            'billing_profile' => ['sometimes', 'array'],
        ]);

        return $this->respond(fn () => $this->createClient->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            kind: $validated['kind'],
            displayName: $validated['display_name'],
            profile: $validated['profile'] ?? [],
            billingProfile: $validated['billing_profile'] ?? null,
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function addContact(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'array'],
            'profile.display_name' => ['required', 'string', 'min:2', 'max:160'],
            'profile.email' => ['sometimes', 'email', 'max:254'],
            'profile.phone' => ['sometimes', 'string', 'max:50'],
            'profile.role' => ['sometimes', 'string', 'max:100'],
            'make_primary' => ['sometimes', 'boolean'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->addContact->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            clientId: $clientId,
            profile: $validated['profile'],
            makePrimary: (bool) ($validated['make_primary'] ?? false),
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ), 201);
    }

    public function contacts(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->listContacts(
            $this->actorId($request),
            $workspaceId,
            $clientId,
        ));
    }

    public function changePrimaryContact(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        $validated = $request->validate([
            'contact_id' => ['present', 'nullable', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->changePrimaryContact->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            clientId: $clientId,
            newPrimaryContactId: $validated['contact_id'],
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function billingContext(Request $request, string $workspaceId, string $clientId): JsonResponse
    {
        return $this->respond(fn () => $this->queries->getClientBillingContext(
            $this->actorId($request),
            $workspaceId,
            $clientId,
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
