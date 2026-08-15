import { apiRequest } from '@/api/client';
import type { AdvisorOverview } from '@/types/api';

export async function getAdvisorOverview(token: string, workspaceId: string): Promise<AdvisorOverview> {
    return apiRequest(
        'GET',
        `/workspaces/${workspaceId}/advisor/overview`,
        undefined,
        { token },
    );
}
