<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Workspace;

use App\Http\Controllers\Controller;
use Atlas\Modules\Identity\Application\ListWorkspaceMembersHandler;
use Atlas\Modules\Workspace\Application\ChangeWorkspacePreferencesHandler;
use Atlas\Modules\Workspace\Application\UpdateWorkspaceBillingIdentityHandler;
use Atlas\Modules\Workspace\Application\UpdateWorkspaceProfileHandler;
use Atlas\Modules\Workspace\Application\WorkspaceSettingsQueryHandler;
use Atlas\Modules\Workspace\Application\WorkspaceSummaryQueryHandler;
use Atlas\Platform\Security\StepUpRequiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceSummaryQueryHandler $summary,
        private readonly WorkspaceSettingsQueryHandler $settings,
        private readonly UpdateWorkspaceProfileHandler $updateProfile,
        private readonly UpdateWorkspaceBillingIdentityHandler $updateBillingIdentity,
        private readonly ChangeWorkspacePreferencesHandler $changePreferences,
        private readonly ListWorkspaceMembersHandler $listMembers,
    ) {}

    public function summary(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->summary->handle(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function profile(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->settings->getProfile(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function updateProfile(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:120'],
            'trading_name' => ['nullable', 'string', 'max:160'],
            'activity_description' => ['nullable', 'string', 'max:2000'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->updateProfile->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            displayName: $validated['display_name'],
            tradingName: $validated['trading_name'] ?? null,
            activityDescription: $validated['activity_description'] ?? null,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function billingIdentity(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->settings->getBillingIdentity(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function updateBillingIdentity(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:160'],
            'administrative_email' => ['nullable', 'email', 'max:254'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->updateBillingIdentity->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            sessionId: (string) $request->attributes->get('session_id'),
            legalName: $validated['legal_name'] ?? null,
            administrativeEmail: $validated['administrative_email'] ?? null,
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function preferences(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->settings->getPreferences(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    public function updatePreferences(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:16'],
            'timezone' => ['required', 'string', 'max:64'],
            'default_currency' => ['required', 'string', 'size:3'],
            'establishment_country' => ['required', 'string', 'size:2'],
            'expected_revision' => ['required', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->changePreferences->handle(
            actorUserId: $this->actorId($request),
            workspaceId: $workspaceId,
            locale: $validated['locale'],
            timezone: $validated['timezone'],
            defaultCurrency: strtoupper($validated['default_currency']),
            establishmentCountry: strtoupper($validated['establishment_country']),
            expectedRevision: (int) $validated['expected_revision'],
            requestId: $request->header('Idempotency-Key') ?? (string) Str::uuid(),
            correlationId: $request->attributes->get('correlation_id'),
        ));
    }

    public function members(Request $request, string $workspaceId): JsonResponse
    {
        return $this->respond(fn () => $this->listMembers->handle(
            $this->actorId($request),
            $workspaceId,
        ));
    }

    private function actorId(Request $request): string
    {
        return (string) $request->attributes->get('authenticated_user_id');
    }

    /** @param callable(): array<string, mixed> $action */
    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (\DomainException $exception) {
            if ($exception instanceof StepUpRequiredException) {
                return response()->json([
                    'error' => 'StepUpRequired',
                    'messages' => [$exception->getMessage()],
                ], 403);
            }

            $status = match ($exception->getMessage()) {
                'Unauthorized.' => 403,
                'Workspace not found.' => 404,
                'Conflict.', 'Idempotency conflict.' => 409,
                default => 422,
            };

            return response()->json([
                'error' => class_basename($exception),
                'messages' => [$exception->getMessage()],
            ], $status);
        }
    }
}
