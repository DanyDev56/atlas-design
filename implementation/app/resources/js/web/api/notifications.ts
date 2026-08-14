import { apiRequest } from '@/api/client';
import type { MarkNotificationReadResponse, NotificationSummary } from '@/types/api';

function workspacePath(workspaceId: string, suffix: string): string {
    return `/workspaces/${workspaceId}${suffix}`;
}

export async function listNotifications(token: string, workspaceId: string): Promise<NotificationSummary[]> {
    const response = await apiRequest<{ notifications: NotificationSummary[] }>(
        'GET',
        workspacePath(workspaceId, '/notifications'),
        undefined,
        { token },
    );

    return response.notifications;
}

export async function markNotificationRead(
    token: string,
    workspaceId: string,
    notificationId: string,
    expectedRevision: number,
): Promise<MarkNotificationReadResponse> {
    return apiRequest(
        'POST',
        workspacePath(workspaceId, `/notifications/${notificationId}/mark-read`),
        { expected_revision: expectedRevision },
        { token },
    );
}
