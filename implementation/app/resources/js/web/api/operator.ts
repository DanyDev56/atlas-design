import { apiRequest } from '@/api/client';

export interface OperatorLoginResponse {
    session_id: string;
    user_id: string;
    display_name: string;
    token: string;
    expires_at: string;
    permissions: string[];
    authentication_strength: string;
    mfa_verified_at: string | null;
    step_up_expires_at: string | null;
}

export interface OperatorSessionContext {
    session_id: string;
    user_id: string;
    expires_at: string;
    permissions: string[];
    read_only: boolean;
    authentication_strength: string;
    mfa_verified_at: string | null;
    step_up_expires_at: string | null;
}

export interface OperatorStepUpResponse {
    authentication_strength: string;
    mfa_verified_at: string;
    step_up_expires_at: string;
}

export async function loginOperator(email: string, password: string, mfaCode: string): Promise<OperatorLoginResponse> {
    return apiRequest<OperatorLoginResponse>(
        'POST',
        '/operator/auth/login',
        { email, password, mfa_code: mfaCode || null },
        { auth: false },
    );
}

export async function elevateOperatorSession(
    token: string,
    email: string,
    password: string,
    mfaCode: string,
): Promise<OperatorStepUpResponse> {
    return apiRequest<OperatorStepUpResponse>(
        'POST',
        '/operator/auth/session/elevate',
        { email, password, mfa_code: mfaCode },
        { token },
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
