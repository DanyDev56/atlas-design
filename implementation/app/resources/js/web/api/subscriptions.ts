import { apiRequest } from '@/api/client';
import type {
    BillingPortalSessionResponse,
    CheckoutSessionResponse,
    SubscriptionBillingInterval,
    SubscriptionOverviewResponse,
} from '@/types/api';

export async function getSubscriptionOverview(
    token: string,
    workspaceId: string,
): Promise<SubscriptionOverviewResponse> {
    return apiRequest('GET', `/workspaces/${workspaceId}/subscription`, undefined, { token });
}

export async function createBillingPortalSession(
    token: string,
    workspaceId: string,
): Promise<BillingPortalSessionResponse> {
    return apiRequest(
        'POST',
        `/workspaces/${workspaceId}/subscription/portal`,
        {},
        { token },
    );
}

export async function createSubscriptionCheckout(
    token: string,
    workspaceId: string,
    billingInterval: SubscriptionBillingInterval,
): Promise<CheckoutSessionResponse> {
    return apiRequest(
        'POST',
        `/workspaces/${workspaceId}/subscription/checkout`,
        { billing_interval: billingInterval },
        { token, idempotency: true },
    );
}
