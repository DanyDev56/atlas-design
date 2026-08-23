export type DataState = 'Data' | 'NoData' | 'InsufficientData' | 'Unavailable';

export interface ApiError {
    error: string;
    messages: string[];
    capability?: string;
    access_level?: string;
    source?: string;
    valid_until?: string | null;
    limit_name?: string;
    limit?: number;
    current?: number;
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

export interface WorkspaceSummaryResponse {
    workspace_id: string;
    display_name: string;
    access_state: string;
    version: number;
}

export interface WorkspaceProfileResponse {
    workspace_id: string;
    display_name: string;
    trading_name: string | null;
    activity_description: string | null;
    profile_version: number;
}

export interface WorkspaceBillingIdentityResponse {
    workspace_id: string;
    legal_name: string | null;
    administrative_email: string | null;
    billing_identity_version: number;
}

export interface WorkspacePreferencesResponse {
    workspace_id: string;
    locale: string;
    timezone: string;
    default_currency: string;
    establishment_country: string;
    preferences_version: number;
}

export interface WorkspaceMember {
    membership_id: string;
    user_id: string;
    email: string;
    display_name: string;
    role: string;
    status: string;
}

export interface WorkspaceMembersResponse {
    members: WorkspaceMember[];
}

export interface WorkspaceInvitation {
    invitation_id: string;
    recipient_email: string;
    role: string;
    status: string;
    delivery_status: string;
    expires_at: string;
    created_at?: string;
    invitation_token?: string;
}

export interface WorkspaceInvitationsResponse {
    invitations: WorkspaceInvitation[];
}

export type SubscriptionBillingInterval = 'Monthly' | 'Annual';

export interface SubscriptionPrice {
    id: string;
    billing_interval: SubscriptionBillingInterval;
    currency: string;
    amount_minor: number;
    status: string;
}

export interface SubscriptionOverviewResponse {
    workspace_id: string;
    catalog: {
        status: string;
        public: boolean;
        plan: {
            id: string;
            code: string;
            version: number;
            display_name: string;
            prices: SubscriptionPrice[];
            capabilities: string[];
            limits: Record<string, number>;
        };
    };
    trial: {
        id: string;
        status: string;
        started_at: string;
        ends_at: string;
        remaining_days: number;
    } | null;
    subscription: {
        id: string;
        status: 'Active' | 'PastDue' | 'Canceled';
        plan_price_id: string;
        provider: string;
        current_period_start: string;
        current_period_end: string;
        cancel_at_period_end: boolean;
        canceled_at: string | null;
    } | null;
    access: {
        level: 'Full' | 'Restricted' | 'Provisioning';
        source: string;
        valid_until: string | null;
        capabilities: string[];
        limits: Record<string, number>;
    };
    commercialization: {
        enforcement_enabled: boolean;
        checkout_enabled: boolean;
        gateway: string;
    };
}

export interface CheckoutSessionResponse {
    checkout_session_id: string;
    checkout_url: string;
    provider: string;
    mode: 'Preview';
}

export interface InvitationAcceptanceResponse {
    invitation_id: string;
    workspace_id: string;
    membership_id: string;
    status: 'Accepted';
}

export interface SessionContextResponse {
    user_id: string;
    workspace_id: string | null;
    elevation_expires_at: string | null;
}

export interface SessionElevationResponse {
    session_id: string;
    elevation_scope: string;
    elevation_expires_at: string;
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
    measured_activity: DashboardWidget<MeasuredActivityPayload>;
    notifications: DashboardWidget<NotificationUnread>;
}

export interface AdvisorRecommendation {
    recommendation_id: string;
    recommendation_key: string;
    rule_key: string;
    status: string;
    revision: number;
    priority: string;
    rank_score: number;
    action_module: string;
    route_key: string;
    impact: string;
    urgency: string;
    confidence: string;
    effort: string;
    valid_until: string;
    generated_at: string;
    terminal_decision: Record<string, string> | null;
    terminal_at: string | null;
}

export type AdvisorDismissalReason = 'NotRelevant' | 'AlreadyDone' | 'NotNow' | 'IncorrectContext' | 'Other';

export interface AdvisorDecisionResponse {
    recommendation_id: string;
    status: 'Completed' | 'Dismissed';
    revision: number;
    advisor_overview_version: number;
    primary_recommendation_id: string | null;
}

export interface AdvisorOverview {
    advisor_overview_version: number;
    source_eligibility: 'Eligible' | 'InsufficientAssessment' | 'StaleAssessment';
    primary_recommendation: AdvisorRecommendation | null;
    alternative_recommendations: AdvisorRecommendation[];
    updated_at: string;
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

export interface AnalyticsSnapshotPublication {
    analytics_snapshot_id: string;
    profile_key: string;
    profile_version: string;
    freshness_status: 'Current' | 'Lagging';
    completeness_status: string;
    as_of: string;
}

export interface AnalyticsMetricObservation {
    window_kind: string;
    value_status: 'Available' | 'NoData' | string;
    values_by_currency?: Record<string, number>;
    count?: number;
}

export interface MeasuredActivityPayload {
    overview_profile_key: string;
    overview_profile_version: string;
    analytics_snapshot_id: string;
    as_of: string;
    freshness_status: string;
    completeness_status: string;
    metrics: Record<string, AnalyticsMetricObservation>;
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
        overdue?: boolean;
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
    archived_at: string | null;
}

export interface ClientDetail extends ClientSummary {
    workspace_id: string;
    profile: Record<string, unknown>;
    billing_profile: ClientBillingProfile | null;
    primary_contact_id: string | null;
    profile_version: number;
    billing_profile_version: number;
}

export interface ClientBillingIdentifier {
    type: string;
    value: string;
}

export interface ClientBillingAddress {
    line1?: string;
    line2?: string;
    postal_code?: string;
    city?: string;
    country_code?: string;
}

export interface ClientBillingProfile {
    billing_name?: string;
    billing_email?: string;
    billing_address?: ClientBillingAddress;
    registration_identifiers?: ClientBillingIdentifier[];
    tax_identifiers?: ClientBillingIdentifier[];
}

export interface ContactSummary {
    contact_id: string;
    client_id: string;
    profile: Record<string, unknown>;
    status: string;
    is_primary: boolean;
    version: number;
    created_at: string;
    archived_at: string | null;
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
    win_source: 'Manual' | 'AcceptedQuote' | null;
    won_quote_id: string | null;
    won_by: string | null;
    won_at: string | null;
    loss_reason_code: 'Budget' | 'Timing' | 'Competitor' | 'NoDecision' | 'Other' | null;
    loss_note: string | null;
    lost_at: string | null;
}

export interface ClientHistoryImportRecord {
    line: number;
    external_id: string;
    kind: 'Individual' | 'Organization' | string;
    status: 'Active' | 'Archived' | string;
    profile: {
        display_name: string;
        legal_name?: string;
        email?: string;
        phone?: string;
        website?: string;
    };
    source_created_at: string;
    canonical_record_hash: string;
    validation_status: 'Valid' | 'Invalid';
}

export interface ClientHistoryImportValidationError {
    line: number;
    field: string;
    code: string;
    message: string;
}

export interface ClientHistoryImportDuplicateCandidate {
    line: number;
    display_name: string;
    kind: 'Package' | 'ExistingClient';
    matched_line?: number;
    matched_client_id?: string;
    matched_display_name?: string;
}

export interface ClientHistoryImportPreview {
    preview_id: string;
    schema_version: '1.0';
    source_system: string;
    source_exported_at: string;
    package_hash: string;
    row_count: number;
    valid_row_count: number;
    validation_error_count: number;
    duplicate_candidate_count: number;
    valid_for_confirmation: boolean;
    records: ClientHistoryImportRecord[];
    validation_errors: ClientHistoryImportValidationError[];
    duplicate_candidates: ClientHistoryImportDuplicateCandidate[];
    expires_at: string;
}

export type ActivityKind = 'Note' | 'Call' | 'Meeting' | 'Email';

export interface ClientActivity {
    activity_id: string;
    client_id: string;
    contact_id: string | null;
    opportunity_id: string | null;
    kind: ActivityKind;
    summary: string;
    occurred_at: string;
    status: 'Recorded';
    version: number;
}

export interface ActivityAuditContent {
    revision: number;
    kind: ActivityKind;
    summary: string;
    occurred_at: string;
}

export interface ActivityAuditCorrection extends ActivityAuditContent {
    reason: string;
    actor_user_id: string;
    corrected_at: string;
}

export interface ActivityAuditEntry {
    activity_id: string;
    client_id: string;
    contact_id: string | null;
    opportunity_id: string | null;
    status: 'Recorded' | 'Removed';
    aggregate_version: number;
    current_content: ActivityAuditContent;
    corrections: ActivityAuditCorrection[];
    removal: {
        reason: string;
        actor_user_id: string;
        removed_at: string;
    } | null;
    created_at: string;
    updated_at: string;
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
    original_number?: string | null;
    is_historical_import?: boolean;
}

export interface QuoteDetail extends QuoteSummary {
    lines: QuoteLine[];
    invoice_id: string | null;
    deposit_invoice_id?: string | null;
    final_invoice_id?: string | null;
    deposit_invoice_status?: string | null;
    email_delivery_status: 'Pending' | 'Retrying' | 'Accepted' | 'Cancelled' | 'Failed' | null;
    email_delivery_updated_at: string | null;
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
    delivery_status: 'Pending';
    resent: boolean;
}

export interface InvoiceSummary {
    invoice_id: string;
    client_id: string;
    quote_id: string | null;
    kind?: 'Final' | 'Deposit';
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
    last_reminded_at?: string | null;
    reminder_count?: number;
    overdue?: boolean;
    overdue_at?: string | null;
    original_number?: string | null;
    is_historical_import?: boolean;
}

export interface InvoiceDetail extends InvoiceSummary {
    lines: QuoteLine[];
    credit_notes: CreditNote[];
    email_delivery_status: 'Pending' | 'Retrying' | 'Accepted' | 'Cancelled' | 'Failed' | null;
    email_delivery_updated_at: string | null;
}

export interface CreditNote {
    credit_note_id: string;
    invoice_id: string;
    status: 'Draft' | 'Issued' | 'Applied' | 'Discarded';
    credit_note_number: string | null;
    original_number?: string | null;
    net_amount_cents?: number | null;
    tax_amount_cents?: number | null;
    gross_amount_cents?: number | null;
    lines: QuoteLine[];
    total_cents: number;
    amount_applied_cents: number;
    unapplied_amount_cents: number;
    remainder_disposition: 'RefundDue' | 'ClientCredit' | null;
    currency: string;
    reason: string | null;
    version: number;
    is_historical_import?: boolean;
    issued_at?: string | null;
    applied_at?: string | null;
}

export interface ApplyCreditNoteResponse extends CreditNote {
    invoice_balance_cents: number;
    invoice_settlement_status: string;
    invoice_version: number;
}

export interface PaymentResponse {
    payment_id: string;
    invoice_id: string;
    amount_cents: number;
    balance_cents: number;
    settlement_status: string;
    version: number;
}

export type BillingHistoryImportRecordKind = 'quotes' | 'invoices' | 'payments' | 'credit_notes' | 'package';

export interface BillingHistoryImportCounts {
    quotes: number;
    invoices: number;
    payments: number;
    credit_notes: number;
}

export interface BillingHistoryImportValidationError {
    file: BillingHistoryImportRecordKind;
    line: number;
    field: string;
    code: string;
    message: string;
}

export interface BillingHistoryImportResolvedClient {
    client_external_id: string;
    client_id: string;
    display_name?: string;
}

export interface BillingHistoryImportRecord {
    line: number;
    external_id: string;
    client_external_id?: string;
    client_id?: string;
    client_display_name?: string;
    original_number?: string;
    validation_status: 'Valid' | 'Invalid';
    [field: string]: string | number | undefined;
}

export interface BillingHistoryImportPreview {
    preview_id: string;
    schema_version: string;
    source_system: string;
    source_exported_at: string;
    package_hash: string;
    quote_count: number;
    invoice_count: number;
    payment_count: number;
    credit_note_count: number;
    validation_error_count: number;
    valid_for_confirmation: boolean;
    quotes: BillingHistoryImportRecord[];
    invoices: BillingHistoryImportRecord[];
    payments: BillingHistoryImportRecord[];
    credit_notes: BillingHistoryImportRecord[];
    validation_errors: BillingHistoryImportValidationError[];
    expires_at: string;
}

export type BillingHistoryImportPhase =
    | 'Pending'
    | 'Quotes'
    | 'Invoices'
    | 'Payments'
    | 'CreditNotes'
    | 'Validate'
    | 'Completed'
    | 'Failed';

export interface BillingHistoryImportRun {
    import_run_id: string;
    status: 'Pending' | 'Processing' | 'Completed' | 'Failed' | 'Conflict';
    checkpoint: BillingHistoryImportPhase;
    quote_count?: number;
    invoice_count?: number;
    payment_count?: number;
    credit_note_count?: number;
    processed_quotes?: number;
    processed_invoices?: number;
    processed_payments?: number;
    processed_credit_notes?: number;
    error?: string | null;
}
