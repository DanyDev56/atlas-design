import { apiRequest } from '@/api/client';
import type {
    DashboardResponse,
    LoginResponse,
    RegisterResponse,
    WorkspaceBootstrapResponse,
} from '@/types/api';

export async function login(email: string, password: string): Promise<LoginResponse> {
    return apiRequest<LoginResponse>('POST', '/auth/login', { email, password }, { auth: false });
}

export async function register(
    email: string,
    displayName: string,
    password: string,
): Promise<RegisterResponse> {
    return apiRequest<RegisterResponse>(
        'POST',
        '/auth/register',
        {
            email,
            display_name: displayName,
            password,
            debug_verification_token: true,
        },
        { auth: false, idempotency: true },
    );
}

export async function verifyEmail(userId: string, token: string): Promise<void> {
    await apiRequest('POST', '/auth/verify-email', { user_id: userId, token }, { auth: false });
}

export async function bootstrapWorkspace(token: string, name: string): Promise<WorkspaceBootstrapResponse> {
    return apiRequest<WorkspaceBootstrapResponse>(
        'POST',
        '/workspaces/first',
        { name },
        { token, idempotency: true },
    );
}

export async function fetchDashboard(token: string, workspaceId: string): Promise<DashboardResponse> {
    return apiRequest<DashboardResponse>('GET', `/workspaces/${workspaceId}/dashboard`, undefined, { token });
}

export async function fetchUnreadCount(token: string, workspaceId: string): Promise<{ unread_count: number }> {
    return apiRequest<{ unread_count: number }>(
        'GET',
        `/workspaces/${workspaceId}/notifications/unread-count`,
        undefined,
        { token },
    );
}
