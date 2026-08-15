import { apiRequest } from '@/api/client';
import type { WorkspaceSummaryResponse } from '@/types/api';

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
