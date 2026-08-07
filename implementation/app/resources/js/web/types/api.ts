export type DataState = 'Data' | 'NoData' | 'InsufficientData' | 'Unavailable';

export interface ApiError {
    error: string;
    messages: string[];
}

export interface LoginResponse {
    session_id: string;
    user_id: string;
    token: string;
    expires_at: string;
}

export interface RegisterResponse {
    user_id: string;
    status: string;
    verification_token?: string;
}

export interface WorkspaceBootstrapResponse {
    workspace_id: string;
    status: string;
}

export interface DashboardWidget<T = unknown> {
    source_domain: string;
    data_state: DataState;
    observed_at: string | null;
    payload: T | null;
}

export interface DashboardResponse {
    workspace_id: string;
    advisor_priority: DashboardWidget<AdvisorOverview>;
    business_health: DashboardWidget<BusinessHealthCurrent>;
    pipeline: DashboardWidget<PipelinePayload>;
    billing: DashboardWidget<BillingPayload>;
    notifications: DashboardWidget<NotificationUnread>;
}

export interface AdvisorOverview {
    advisor_overview_version?: number;
    primary_recommendation?: {
        recommendation_key: string;
        priority: string;
        rule_key?: string;
        impact?: string;
    } | null;
    updated_at?: string;
}

export interface BusinessHealthCurrent {
    assessment_status?: string;
    score?: number | null;
    band?: string;
    reliability?: string;
    assessed_at?: string;
}

export interface PipelinePayload {
    counts_by_status?: Record<string, number>;
}

export interface BillingPayload {
    recent_invoices?: Array<{
        invoice_id: string;
        number?: string;
        status: string;
        total_cents?: number;
        currency?: string;
    }>;
}

export interface NotificationUnread {
    unread_count?: number;
}

export interface SessionState {
    token: string;
    userId: string;
    workspaceId: string | null;
    email: string;
}
