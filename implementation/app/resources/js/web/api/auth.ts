import { apiRequest } from '@/api/client';
import type {
    DashboardResponse,
    LoginResponse,
    RegisterResponse,
    SessionContextResponse,
    WorkspaceBootstrapResponse,
} from '@/types/api';

const debugVerificationTokensEnabled =
    import.meta.env.VITE_DEBUG_VERIFICATION_TOKENS === 'true'
    || (import.meta.env.VITE_DEBUG_VERIFICATION_TOKENS === undefined && import.meta.env.DEV);

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
            ...(debugVerificationTokensEnabled ? { debug_verification_token: true } : {}),
        },
        { auth: false, idempotency: true },
    );
}

export async function verifyEmail(userId: string, token: string): Promise<void> {
    await apiRequest('POST', '/auth/verify-email', { user_id: userId, token }, { auth: false });
}

export async function fetchSessionContext(token: string): Promise<SessionContextResponse> {
    return apiRequest<SessionContextResponse>('GET', '/auth/session/context', undefined, { token });
}

export async function revokeSession(token: string): Promise<void> {
    await apiRequest('POST', '/auth/session/revoke', {}, { token, idempotency: true });
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
