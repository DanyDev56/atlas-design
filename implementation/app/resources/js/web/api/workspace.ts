import { apiRequest } from '@/api/client';
import type {
    WorkspaceBillingIdentityResponse,
    WorkspaceMembersResponse,
    WorkspacePreferencesResponse,
    WorkspaceProfileResponse,
    WorkspaceSummaryResponse,
} from '@/types/api';

export async function getWorkspaceSummary(
    token: string,
    workspaceId: string,
): Promise<WorkspaceSummaryResponse> {
    return apiRequest(
        'GET',
        `/workspaces/${workspaceId}/summary`,
        undefined,
        { token },
    );
}

export async function getWorkspaceProfile(
    token: string,
    workspaceId: string,
): Promise<WorkspaceProfileResponse> {
    return apiRequest('GET', `/workspaces/${workspaceId}/profile`, undefined, { token });
}

export async function updateWorkspaceProfile(
    token: string,
    workspaceId: string,
    payload: {
        display_name: string;
        trading_name: string | null;
        activity_description: string | null;
        expected_revision: number;
    },
): Promise<WorkspaceProfileResponse> {
    return apiRequest('PATCH', `/workspaces/${workspaceId}/profile`, payload, { token, idempotency: true });
}

export async function getWorkspaceBillingIdentity(
    token: string,
    workspaceId: string,
): Promise<WorkspaceBillingIdentityResponse> {
    return apiRequest('GET', `/workspaces/${workspaceId}/billing-identity`, undefined, { token });
}

export async function updateWorkspaceBillingIdentity(
    token: string,
    workspaceId: string,
    payload: {
        legal_name: string | null;
        administrative_email: string | null;
        expected_revision: number;
    },
): Promise<WorkspaceBillingIdentityResponse> {
    return apiRequest('PATCH', `/workspaces/${workspaceId}/billing-identity`, payload, { token, idempotency: true });
}

export async function getWorkspacePreferences(
    token: string,
    workspaceId: string,
): Promise<WorkspacePreferencesResponse> {
    return apiRequest('GET', `/workspaces/${workspaceId}/preferences`, undefined, { token });
}

export async function updateWorkspacePreferences(
    token: string,
    workspaceId: string,
    payload: {
        locale: string;
        timezone: string;
        default_currency: string;
        establishment_country: string;
        expected_revision: number;
    },
): Promise<WorkspacePreferencesResponse> {
    return apiRequest('PATCH', `/workspaces/${workspaceId}/preferences`, payload, { token, idempotency: true });
}

export async function listWorkspaceMembers(
    token: string,
    workspaceId: string,
): Promise<WorkspaceMembersResponse> {
    return apiRequest('GET', `/workspaces/${workspaceId}/members`, undefined, { token });
}
