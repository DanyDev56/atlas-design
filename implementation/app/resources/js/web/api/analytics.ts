import { apiRequest } from '@/api/client';
import type { AnalyticsSnapshotPublication } from '@/types/api';

export async function publishAnalyticsSnapshot(
    token: string,
    workspaceId: string,
): Promise<AnalyticsSnapshotPublication> {
    return apiRequest(
        'POST',
        `/workspaces/${workspaceId}/analytics/snapshots/publish`,
        {},
        { token, idempotency: true },
    );
}
