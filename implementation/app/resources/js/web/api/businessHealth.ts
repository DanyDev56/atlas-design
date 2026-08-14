import { apiRequest } from '@/api/client';
import type { BusinessHealthAssessment } from '@/types/api';

export async function getCurrentBusinessHealth(
    token: string,
    workspaceId: string,
): Promise<BusinessHealthAssessment> {
    return apiRequest(
        'GET',
        `/workspaces/${workspaceId}/business-health/current`,
        undefined,
        { token },
    );
}
