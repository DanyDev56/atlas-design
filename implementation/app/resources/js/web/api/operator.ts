import { apiRequest } from '@/api/client';

export interface OperatorLoginResponse {
    session_id: string;
    user_id: string;
    display_name: string;
    token: string;
    expires_at: string;
    permissions: string[];
}

export interface OperatorSessionContext {
    session_id: string;
    user_id: string;
    expires_at: string;
    permissions: string[];
    read_only: boolean;
}

export async function loginOperator(email: string, password: string): Promise<OperatorLoginResponse> {
    return apiRequest<OperatorLoginResponse>(
        'POST',
        '/operator/auth/login',
        { email, password },
        { auth: false },
    );
}

export async function fetchOperatorSessionContext(token: string): Promise<OperatorSessionContext> {
    return apiRequest<OperatorSessionContext>(
        'GET',
        '/operator/session/context',
        undefined,
        { token },
    );
}

export async function revokeOperatorSession(token: string): Promise<void> {
    await apiRequest(
        'POST',
        '/operator/auth/session/revoke',
        {},
        { token, idempotency: true },
    );
}

