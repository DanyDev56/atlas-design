<?php

declare(strict_types=1);

namespace Atlas\Platform\Laravel\Http\Middleware;

use Atlas\Modules\Subscriptions\Contracts\SubscriptionAccessRestrictedException;
use Atlas\Modules\Subscriptions\Contracts\SubscriptionPolicyUnavailableException;
use Atlas\Modules\Subscriptions\Contracts\WorkspaceEntitlementEnforcer;
use Atlas\Platform\Security\WorkspaceAuthorizer;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireWorkspaceEntitlementMiddleware
{
    public function __construct(
        private readonly WorkspaceEntitlementEnforcer $entitlements,
        private readonly WorkspaceAuthorizer $authorizer,
    ) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $workspaceId = (string) $request->route('workspaceId', '');
        $userId = (string) $request->attributes->get('authenticated_user_id', '');
        if (
            config('subscriptions.enforcement_enabled', false)
            && ($workspaceId === '' || $userId === '' || ! $this->authorizer->hasActiveMembership($userId, $workspaceId))
        ) {
            return response()->json(['error' => 'Forbidden', 'messages' => ['Unauthorized.']], 403);
        }

        try {
            $this->entitlements->enforce($workspaceId, $capability);
        } catch (SubscriptionPolicyUnavailableException $exception) {
            return response()->json([
                'error' => 'SubscriptionPolicyUnavailable',
                'messages' => [$exception->getMessage()],
            ], 503);
        } catch (SubscriptionAccessRestrictedException $exception) {
            return new JsonResponse([
                'error' => 'SubscriptionAccessRestricted',
                'messages' => ['This workspace no longer has access to this capability.'],
                'capability' => $exception->decision->capability,
                'access_level' => $exception->decision->accessLevel,
                'source' => $exception->decision->sourceType,
                'valid_until' => $exception->decision->validUntil,
            ], 402);
        }

        return $next($request);
    }
}
