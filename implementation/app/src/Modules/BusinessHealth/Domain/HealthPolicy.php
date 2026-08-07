<?php

declare(strict_types=1);

namespace Atlas\Modules\BusinessHealth\Domain;

final class HealthPolicy
{
    public const VERSION = '1.0.0';

    public const STATUS_AVAILABLE = 'Available';
    public const STATUS_INSUFFICIENT_DATA = 'InsufficientData';

    public const RELIABILITY_RELIABLE = 'Reliable';
    public const RELIABILITY_LIMITED = 'Limited';
    public const RELIABILITY_INSUFFICIENT = 'Insufficient';

    public const FRESHNESS_CURRENT = 'Current';
    public const COMPLETENESS_COMPLETE = 'Complete';

    public const COMPONENT_QUOTE_ACCEPTANCE = 'quote_acceptance';
    public const COMPONENT_PIPELINE_EVOLUTION = 'pipeline_evolution';
    public const COMPONENT_NET_INVOICED_EVOLUTION = 'net_invoiced_evolution';
    public const COMPONENT_COLLECTED_EVOLUTION = 'collected_evolution';
    public const COMPONENT_OVERDUE_LOAD = 'overdue_load';
    public const COMPONENT_ON_TIME_PAYMENT = 'on_time_payment';
    public const COMPONENT_TOP_CLIENT_SHARE = 'top_client_share';

    public const FACTOR_COMMERCIAL_MOMENTUM = 'CommercialMomentum';
    public const FACTOR_BILLING_MOMENTUM = 'BillingMomentum';
    public const FACTOR_RECEIVABLES_DISCIPLINE = 'ReceivablesDiscipline';
    public const FACTOR_CLIENT_DIVERSIFICATION = 'ClientDiversification';

    public const RISK_OVERDUE_EXPOSURE = 'OverdueExposureRisk';
    public const RISK_CLIENT_CONCENTRATION = 'ClientConcentrationRisk';
    public const RISK_COMMERCIAL_MOMENTUM = 'CommercialMomentumRisk';
    public const RISK_BILLING_MOMENTUM = 'BillingMomentumRisk';

    public const TREND_UNKNOWN = 'Unknown';
}
