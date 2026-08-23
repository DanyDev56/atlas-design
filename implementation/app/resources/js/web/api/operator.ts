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

export type OperatorSourceStatus = 'Available' | 'NotCollected' | 'Unavailable';
export type OperatorCardTone = 'Neutral' | 'Warning' | 'Critical' | 'Muted';

export interface OperatorOverviewValue {
    key: string;
    label: string;
    value: number;
}

export interface OperatorOverviewCard {
    key: string;
    label: string;
    description: string;
    status: OperatorSourceStatus;
    tone: OperatorCardTone;
    values: OperatorOverviewValue[];
    context: string;
    source: string;
    measured_at: string | null;
    freshness: {
        target_seconds: number;
        stale_after_seconds: number;
        state: 'Current' | 'Stale' | 'NotCollected' | 'Unavailable';
    };
    href: string | null;
    detail_permission: string | null;
}

export interface OperatorOverview {
    generated_at: string;
    read_only: boolean;
    actions_enabled: boolean;
    attention_count: number;
    cards: OperatorOverviewCard[];
}

export interface OperatorPage<T> {
    items: T[];
    total: number;
    page: number;
    per_page: number;
    total_pages: number;
}

export interface OperatorOutboxItem {
    event_id: string;
    event_type: string;
    status: 'Pending' | 'Retrying' | 'DeadLetter' | 'Dispatched';
    attempts: number;
    created_at: string;
    available_at: string | null;
    dispatched_at: string | null;
    failed_at: string | null;
}

export interface OperatorEmailItem {
    event_id: string;
    event_type: string;
    template_key: string | null;
    status: 'Pending' | 'Retrying' | 'Failed' | 'Accepted' | 'Cancelled';
    attempts: number;
    provider: string | null;
    created_at: string;
    updated_at: string | null;
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

export async function fetchOperatorOverview(token: string): Promise<OperatorOverview> {
    return apiRequest<OperatorOverview>('GET', '/operator/overview', undefined, { token });
}

export async function fetchOperatorOutbox(
    token: string,
    status: string,
    page: number,
): Promise<OperatorPage<OperatorOutboxItem>> {
    const query = new URLSearchParams({ status, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorOutboxItem>>('GET', `/operator/overview/outbox?${query}`, undefined, { token });
}

export async function fetchOperatorEmails(
    token: string,
    status: string,
    page: number,
): Promise<OperatorPage<OperatorEmailItem>> {
    const query = new URLSearchParams({ status, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorEmailItem>>('GET', `/operator/overview/emails?${query}`, undefined, { token });
}
