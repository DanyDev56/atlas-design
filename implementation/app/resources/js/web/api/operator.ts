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
    actions_enabled: boolean;
    authentication_strength: string;
    mfa_verified_at: string | null;
    step_up_expires_at: string | null;
}

export interface OperatorStepUpResponse {
    authentication_strength: string;
    mfa_verified_at: string;
    step_up_expires_at: string;
}

export interface OperatorManagedSessionItem {
    reference: string;
    operator_reference: string;
    status: 'Active' | 'Expired' | 'Revoked';
    current: boolean;
    authentication_strength: string;
    mfa_verified: boolean;
    step_up_active: boolean;
    created_at: string;
    expires_at: string;
    revoked_at: string | null;
    revision: number;
}

export interface OperatorSessionRevocationPreview {
    reference: string;
    current: { status: 'Active'; revision: number };
    proposed: { status: 'Revoked'; revision: number };
    reason_code: string;
    preview_fingerprint: string;
    effects: string[];
}

export interface OperatorSessionRevocationResult {
    reference: string;
    status: 'Revoked';
    revision: number;
    revoked_at: string;
    replayed: boolean;
}

export type OperatorSourceStatus = 'Available' | 'NoData' | 'NotCollected' | 'Unavailable';
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

export interface OperatorOutboxRetryPreview {
    event_id: string;
    event_type: string;
    current: { status: 'DeadLetter'; attempts: number; failed_at: string };
    proposed: { status: 'Pending'; attempts: 0; availability: 'Immediate' };
    reason_code: string;
    preview_fingerprint: string;
    effects: string[];
}

export interface OperatorOutboxRetryResult {
    event_id: string;
    event_type: string;
    status: 'Pending';
    attempts: 0;
    scheduled_at: string;
    replayed: boolean;
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

export type BillingEnvironment = 'Sandbox' | 'Live' | 'Unknown';

export interface OperatorSubscriptionItem {
    reference: string;
    workspace_reference: string;
    status: 'Active' | 'PastDue' | 'Canceled';
    billing_environment: BillingEnvironment;
    provider: string;
    plan_code: string;
    billing_interval: string;
    current_period_end: string;
    cancel_at_period_end: boolean;
    past_due_since: string | null;
    last_provider_event_at: string;
}

export interface OperatorSubscriptionWebhookItem {
    reference: string;
    event_type: string;
    status: 'Received' | 'Processing' | 'Processed' | 'Ignored' | 'Deferred' | 'Failed';
    billing_environment: BillingEnvironment;
    provider: string;
    attempts: number;
    occurred_at: string;
    received_at: string;
    processed_at: string | null;
}

export interface OperatorRuntimeSnapshot {
    database_available: boolean;
    roles: Record<'api' | 'worker' | 'scheduler', { status: 'Current' | 'Stale' | 'NotCollected'; recorded_at: string | null; age_seconds: number | null }>;
}

export interface OperatorMaintenanceItem {
    reference: string;
    kind: 'Backup' | 'RestoreCanary';
    status: 'Succeeded' | 'Failed';
    size_bytes: number | null;
    started_at: string;
    completed_at: string;
}

export interface OperatorHttpMetricItem {
    method: string;
    route_template: string;
    request_count: number;
    error_count: number;
    error_rate_percent: number | null;
    average_duration_ms: number | null;
    maximum_duration_ms: number;
    measured_at: string;
}

export interface OperatorHttpMetricPage extends OperatorPage<OperatorHttpMetricItem> {
    window_minutes: number;
}

export interface OperatorAlertItem {
    key: string;
    state: 'Healthy' | 'Firing';
    context: Record<string, string | number | boolean | null>;
    first_detected_at: string | null;
    last_evaluated_at: string;
    last_notified_at: string | null;
    resolved_at: string | null;
}

export interface OperatorSupportCaseItem {
    reference: string;
    workspace_reference: string;
    requester_reference: string;
    category: 'Access' | 'Security' | 'Billing' | 'DataRequest' | 'Product' | 'Other';
    severity: 'P0' | 'P1' | 'P2' | 'P3';
    status: 'Open' | 'Acknowledged' | 'InProgress' | 'WaitingRequester' | 'Resolved' | 'Closed';
    assigned: boolean;
    revision: number;
    requester_verified: boolean;
    ownership_verified: boolean;
    summary_code: string;
    response_due_at: string;
    opened_at: string;
    resolved_at: string | null;
    event_count: number;
}

export interface OperatorSupportCaseProposal {
    expected_revision: number;
    status: 'Keep' | 'Acknowledged' | 'InProgress' | 'WaitingRequester' | 'Resolved' | 'Closed';
    assignment: 'Keep' | 'Self' | 'Unassigned';
    reason_code: string;
}

export interface OperatorSupportCasePreview {
    reference: string;
    current: { status: string; assignment: string; revision: number };
    proposed: { status: string; assignment: string; revision: number };
    reason_code: string;
    preview_fingerprint: string;
    effects: string[];
}

export interface OperatorSupportCaseActionResult {
    reference: string;
    status: string;
    assignment: string;
    revision: number;
    updated_at: string;
    replayed: boolean;
}

export interface OperatorDataRequestItem {
    reference: string;
    support_reference: string;
    workspace_reference: string;
    requester_reference: string;
    request_type: 'Access' | 'Rectification' | 'Erasure' | 'Restriction' | 'Objection' | 'Portability';
    status: 'Received' | 'IdentityPending' | 'Qualified' | 'InPreparation' | 'AwaitingApproval' | 'Delivered' | 'Rejected' | 'Closed';
    identity_verified: boolean;
    ownership_verified: boolean;
    due_at: string;
    decision_code: string | null;
    delivery_expires_at: string | null;
    created_at: string;
}

export interface OperatorPolicyItem {
    document_kind: 'BetaTerms' | 'PrivacyNotice';
    version: string;
    lifecycle: 'Draft' | 'Published';
    effective_at: string | null;
    recorded_at: string;
    proof_count: number;
}

export interface OperatorPolicyPage extends OperatorPage<OperatorPolicyItem> {
    consent_counts: Record<'Interview' | 'Recording' | 'PublicQuote', number>;
}

export interface BetaFunnelStep {
    stage: string;
    reached: number;
    denominator: number;
    rate_percent: number | null;
    median_duration_hours: number | null;
}

export interface BetaCohortOverview {
    generated_at: string;
    status: 'Available' | 'NoData';
    participant_count: number;
    active_count: number;
    blocked_count: number;
    decision_count: number;
    funnel: BetaFunnelStep[];
    pricing_cells: Array<{ cell: string; participants: number; decisions: number; positive: number }>;
    source: string;
}

export interface BetaParticipant {
    beta_code: string;
    status: 'Active' | 'Exited';
    pricing_cell: 'P19' | 'P24' | 'P29';
    packaging_version: string;
    segment: string;
    channel: string;
    current_stage: string;
    current_stage_at: string;
    stage_dates: Record<string, string | null>;
    first_value_path: 'Analysis' | 'Document' | null;
    stalled_days: number;
    blocked: boolean;
    latest_review: null | {
        milestone: string;
        blockage_code: string | null;
        support_minutes: number;
        next_action: string;
        reviewed_at: string;
    };
    pricing_decision: null | {
        decision: string;
        primary_reason: string;
        preference: string;
        observed_on: string;
        has_evidence: boolean;
    };
}

export interface BetaParticipantDiagnostic {
    participant: BetaParticipant;
    diagnostic: {
        beta_code: string;
        analytics: {
            status: 'Available' | 'NoData';
            published_at: string | null;
            freshness_status: string | null;
            completeness_status: string | null;
        };
        business_health: {
            status: 'Available' | 'NoData';
            assessed_at: string | null;
            assessment_status: string | null;
            reliability: string | null;
            coverage_percent: number | null;
        };
        source: string;
    };
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

export async function fetchManagedOperatorSessions(
    token: string,
    status: string,
    page: number,
): Promise<OperatorPage<OperatorManagedSessionItem>> {
    const query = new URLSearchParams({ status, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorManagedSessionItem>>('GET', `/operator/security/sessions?${query}`, undefined, { token });
}

export async function previewOperatorSessionRevocation(
    token: string,
    reference: string,
    expectedRevision: number,
    reasonCode: string,
): Promise<OperatorSessionRevocationPreview> {
    return apiRequest<OperatorSessionRevocationPreview>('POST', `/operator/security/sessions/${reference}/preview`, {
        expected_revision: expectedRevision,
        reason_code: reasonCode,
    }, { token });
}

export async function revokeManagedOperatorSession(
    token: string,
    reference: string,
    expectedRevision: number,
    reasonCode: string,
    previewFingerprint: string,
    idempotencyKey: string,
): Promise<OperatorSessionRevocationResult> {
    return apiRequest<OperatorSessionRevocationResult>('PATCH', `/operator/security/sessions/${reference}`, {
        expected_revision: expectedRevision,
        reason_code: reasonCode,
        preview_fingerprint: previewFingerprint,
    }, { token, idempotencyKey });
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

export async function previewOperatorOutboxRetry(
    token: string,
    eventId: string,
    reasonCode: string,
): Promise<OperatorOutboxRetryPreview> {
    return apiRequest<OperatorOutboxRetryPreview>('POST', `/operator/outbox/${eventId}/retry-preview`, {
        reason_code: reasonCode,
    }, { token });
}

export async function retryOperatorOutboxMessage(
    token: string,
    eventId: string,
    reasonCode: string,
    previewFingerprint: string,
    idempotencyKey: string,
): Promise<OperatorOutboxRetryResult> {
    return apiRequest<OperatorOutboxRetryResult>('PATCH', `/operator/outbox/${eventId}/retry`, {
        reason_code: reasonCode,
        preview_fingerprint: previewFingerprint,
    }, { token, idempotencyKey });
}

export async function fetchOperatorEmails(
    token: string,
    status: string,
    page: number,
): Promise<OperatorPage<OperatorEmailItem>> {
    const query = new URLSearchParams({ status, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorEmailItem>>('GET', `/operator/overview/emails?${query}`, undefined, { token });
}

export async function fetchOperatorSubscriptions(
    token: string,
    status: string,
    environment: string,
    page: number,
): Promise<OperatorPage<OperatorSubscriptionItem>> {
    const query = new URLSearchParams({ status, environment, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorSubscriptionItem>>('GET', `/operator/overview/subscriptions?${query}`, undefined, { token });
}

export async function fetchOperatorSubscriptionWebhooks(
    token: string,
    status: string,
    environment: string,
    page: number,
): Promise<OperatorPage<OperatorSubscriptionWebhookItem>> {
    const query = new URLSearchParams({ status, environment, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorSubscriptionWebhookItem>>('GET', `/operator/overview/subscription-webhooks?${query}`, undefined, { token });
}

export async function fetchOperatorRuntime(token: string): Promise<OperatorRuntimeSnapshot> {
    return apiRequest<OperatorRuntimeSnapshot>('GET', '/operator/overview/runtime', undefined, { token });
}

export async function fetchOperatorMaintenance(token: string, page: number): Promise<OperatorPage<OperatorMaintenanceItem>> {
    const query = new URLSearchParams({ kind: 'All', status: 'All', page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorMaintenanceItem>>('GET', `/operator/overview/maintenance?${query}`, undefined, { token });
}

export async function fetchOperatorHttpMetrics(token: string, window: number): Promise<OperatorHttpMetricPage> {
    const query = new URLSearchParams({ window: String(window), page: '1', per_page: '20' });
    return apiRequest<OperatorHttpMetricPage>('GET', `/operator/overview/http?${query}`, undefined, { token });
}

export async function fetchOperatorAlerts(token: string): Promise<OperatorPage<OperatorAlertItem>> {
    const query = new URLSearchParams({ state: 'All', page: '1', per_page: '20' });
    return apiRequest<OperatorPage<OperatorAlertItem>>('GET', `/operator/overview/alerts?${query}`, undefined, { token });
}

export async function fetchOperatorSupport(
    token: string,
    status: string,
    severity: string,
    page: number,
): Promise<OperatorPage<OperatorSupportCaseItem>> {
    const query = new URLSearchParams({ status, severity, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorSupportCaseItem>>('GET', `/operator/overview/support?${query}`, undefined, { token });
}

export async function previewOperatorSupportCase(
    token: string,
    reference: string,
    proposal: OperatorSupportCaseProposal,
): Promise<OperatorSupportCasePreview> {
    return apiRequest<OperatorSupportCasePreview>('POST', `/operator/support/${reference}/preview`, proposal, { token });
}

export async function updateOperatorSupportCase(
    token: string,
    reference: string,
    proposal: OperatorSupportCaseProposal,
    previewFingerprint: string,
    idempotencyKey: string,
): Promise<OperatorSupportCaseActionResult> {
    return apiRequest<OperatorSupportCaseActionResult>('PATCH', `/operator/support/${reference}`, {
        ...proposal,
        preview_fingerprint: previewFingerprint,
    }, { token, idempotencyKey });
}

export async function fetchOperatorDataRequests(
    token: string,
    status: string,
    type: string,
    page: number,
): Promise<OperatorPage<OperatorDataRequestItem>> {
    const query = new URLSearchParams({ status, type, page: String(page), per_page: '20' });
    return apiRequest<OperatorPage<OperatorDataRequestItem>>('GET', `/operator/overview/data-requests?${query}`, undefined, { token });
}

export async function fetchOperatorCompliance(token: string, page: number): Promise<OperatorPolicyPage> {
    const query = new URLSearchParams({ page: String(page), per_page: '20' });
    return apiRequest<OperatorPolicyPage>('GET', `/operator/overview/compliance?${query}`, undefined, { token });
}

export async function fetchBetaCohortOverview(token: string): Promise<BetaCohortOverview> {
    return apiRequest<BetaCohortOverview>('GET', '/operator/beta/overview', undefined, { token });
}

export async function fetchBetaParticipants(
    token: string,
    filters: { stage: string; cell: string; status: string; page: number },
): Promise<OperatorPage<BetaParticipant>> {
    const query = new URLSearchParams({
        stage: filters.stage,
        cell: filters.cell,
        status: filters.status,
        page: String(filters.page),
        per_page: '20',
    });
    return apiRequest<OperatorPage<BetaParticipant>>('GET', `/operator/beta/participants?${query}`, undefined, { token });
}

export async function fetchBetaParticipantDiagnostic(token: string, betaCode: string): Promise<BetaParticipantDiagnostic> {
    return apiRequest<BetaParticipantDiagnostic>('GET', `/operator/beta/participants/${encodeURIComponent(betaCode)}`, undefined, { token });
}
