import { apiRequest } from '@/api/client';
import type { AdvisorDecisionResponse, AdvisorDismissalReason, AdvisorOverview } from '@/types/api';

export async function getAdvisorOverview(token: string, workspaceId: string): Promise<AdvisorOverview> {
    return apiRequest(
        'GET',
        `/workspaces/${workspaceId}/advisor/overview`,
        undefined,
        { token },
    );
}

export async function completeAdvisorRecommendation(
    token: string,
    workspaceId: string,
    recommendationId: string,
    expectedRevision: number,
): Promise<AdvisorDecisionResponse> {
    return apiRequest(
        'POST',
        `/workspaces/${workspaceId}/advisor/recommendations/${recommendationId}/complete`,
        {
            completion_confirmation: 'UserConfirmedActionCompleted',
            expected_revision: expectedRevision,
        },
        { token },
    );
}

export async function dismissAdvisorRecommendation(
    token: string,
    workspaceId: string,
    recommendationId: string,
    reason: AdvisorDismissalReason,
    expectedRevision: number,
): Promise<AdvisorDecisionResponse> {
    return apiRequest(
        'POST',
        `/workspaces/${workspaceId}/advisor/recommendations/${recommendationId}/dismiss`,
        {
            dismissal_reason: reason,
            expected_revision: expectedRevision,
        },
        { token },
    );
}
