<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Application;

use Atlas\Modules\Subscriptions\Contracts\RecurringBillingGateway;
use Atlas\Modules\Subscriptions\Domain\SubscriptionRepository;
use Atlas\Platform\Security\WorkspaceAuthorizer;

final readonly class CreateBillingPortalSessionHandler
{
    public function __construct(
        private WorkspaceAuthorizer $authorizer,
        private SubscriptionRepository $subscriptions,
        private RecurringBillingGateway $gateway,
    ) {}

    /** @return array<string, string> */
    public function handle(string $actorUserId, string $workspaceId): array
    {
        $this->authorizer->authorize($actorUserId, $workspaceId, 'subscriptions.manage');

        $subscription = $this->subscriptions->findByWorkspaceId($workspaceId);
        if ($subscription === null || $subscription->provider() !== config('subscriptions.gateway', 'fake')) {
            throw new \DomainException('Billing portal unavailable.');
        }

        $returnUrl = rtrim((string) config('app.url'), '/').'/app/settings/subscription';
        $session = $this->gateway->createPortalSession(
            workspaceId: $workspaceId,
            providerSubscriptionReference: $subscription->providerReference(),
            returnUrl: $returnUrl,
        );

        return [
            'portal_session_id' => $session->id,
            'portal_url' => $session->url,
            'provider' => $session->provider,
        ];
    }
}
