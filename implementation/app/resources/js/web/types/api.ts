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

export interface SessionContextResponse {
    user_id: string;
    workspace_id: string | null;
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
        action_module?: string;
        route_key?: string;
        impact?: string;
        urgency?: string;
        confidence?: string;
        effort?: string;
        valid_until?: string;
    } | null;
    updated_at?: string;
}

export interface BusinessHealthCurrent {
    assessment_status?: string;
    overall_score?: number | null;
    health_band?: string | null;
    assessment_reliability?: string;
    health_trend?: string | null;
    global_coverage_percent?: number;
    assessed_at?: string;
}

export interface BusinessHealthComponent {
    status: 'Available' | 'Unavailable';
    score?: number;
    reason?: string;
}

export interface BusinessHealthFactor {
    status: 'Available' | 'Unavailable';
    score?: number;
    coverage_percent: number;
}

export interface BusinessHealthRisk {
    risk_key: string;
    severity: 'Low' | 'Medium' | 'High' | 'Critical';
}

export interface BusinessHealthAssessment {
    business_health_assessment_id: string;
    workspace_id: string;
    analytics_snapshot_id: string;
    health_policy_version: string;
    as_of: string;
    assessed_at: string;
    assessment_status: 'Available' | 'InsufficientData';
    assessment_reliability: 'Reliable' | 'Limited' | 'Insufficient';
    overall_score: number | null;
    health_band: string | null;
    health_trend: string;
    primary_attention: {
        factor_key: string;
        deficit_contribution: number;
    } | null;
    components: Record<string, BusinessHealthComponent>;
    factors: Record<string, BusinessHealthFactor>;
    risks: BusinessHealthRisk[];
    global_coverage_percent: number;
    assessment_currency: string | null;
    source_published_at: string;
}

export interface PipelinePayload {
    counts_by_status?: Record<string, number>;
}

export interface BillingPayload {
    recent_invoices?: Array<{
        invoice_id: string;
        invoice_number?: string | null;
        status: string;
        settlement_status?: string;
        total_cents?: number;
        balance_cents?: number;
        currency?: string;
    }>;
}

export interface NotificationUnread {
    unread_count?: number;
}

export interface NotificationContent {
    template_key: string;
    template_version?: string;
    recommendation_key?: string | null;
    action_module?: string | null;
    route_key?: string | null;
}

export interface NotificationSummary {
    notification_id: string;
    workspace_id: string;
    recommendation_id: string | null;
    notification_topic: string;
    status: 'Active' | 'Resolved' | 'Superseded' | 'Expired';
    read_state: 'Unread' | 'Read';
    priority: string;
    content: NotificationContent;
    selected_channels: string[];
    revision: number;
    created_at: string;
    display_until: string | null;
}

export interface MarkNotificationReadResponse {
    notification_id: string;
    revision: number;
}

export interface SessionState {
    token: string;
    userId: string;
    workspaceId: string | null;
    email: string;
    expiresAt: string;
}

export interface ClientSummary {
    client_id: string;
    display_name: string;
    kind: 'Individual' | 'Organization';
    status: string;
    version: number;
}

export interface ClientDetail extends ClientSummary {
    workspace_id: string;
    profile: Record<string, unknown>;
    billing_profile: Record<string, unknown> | null;
    primary_contact_id: string | null;
    profile_version: number;
    billing_profile_version: number;
}

export interface OpportunitySummary {
    opportunity_id: string;
    client_id: string;
    title: string;
    status: string;
    estimated_amount_cents: number | null;
    currency: string;
    version: number;
}

export interface OpportunityDetail extends OpportunitySummary {
    workspace_id: string;
    contact_id: string | null;
    qualified_at: string | null;
}

export interface QuoteLine {
    description: string;
    quantity: number;
    unit_price_cents: number;
}

export interface QuoteSummary {
    quote_id: string;
    client_id: string;
    opportunity_id: string | null;
    status: string;
    total_cents: number;
    currency: string;
    version: number;
}

export interface QuoteDetail extends QuoteSummary {
    lines: QuoteLine[];
    invoice_id: string | null;
}

export interface PublicQuoteDetail {
    quote_id: string;
    client_display_name: string | null;
    status: string;
    lines: QuoteLine[];
    total_cents: number;
    currency: string;
    version: number;
    valid_until: string | null;
}

export interface SendQuoteResponse {
    quote_id: string;
    status: string;
    version: number;
    public_accept_token: string;
}

export interface InvoiceSummary {
    invoice_id: string;
    client_id: string;
    quote_id: string | null;
    status: string;
    settlement_status: string;
    invoice_number: string | null;
    total_cents: number;
    balance_cents: number;
    currency: string;
    version: number;
    issued_at: string | null;
    sent_at: string | null;
    due_date: string | null;
    paid_at: string | null;
}

export interface InvoiceDetail extends InvoiceSummary {
    lines: QuoteLine[];
}

export interface PaymentResponse {
    payment_id: string;
    invoice_id: string;
    amount_cents: number;
    balance_cents: number;
    settlement_status: string;
    version: number;
}
