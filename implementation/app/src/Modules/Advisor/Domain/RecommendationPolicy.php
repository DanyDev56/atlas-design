<?php

declare(strict_types=1);

namespace Atlas\Modules\Advisor\Domain;

final class RecommendationPolicy
{
    public const VERSION = '1.0.0';

    public const ELIGIBILITY_ELIGIBLE = 'Eligible';
    public const ELIGIBILITY_INSUFFICIENT = 'InsufficientAssessment';
    public const ELIGIBILITY_STALE = 'StaleAssessment';

    public const STATUS_GENERATED = 'Generated';

    public const RULE_COLLECT_OVERDUE = 'CollectOverdueInvoices';
    public const RULE_REDUCE_CONCENTRATION = 'ReduceClientConcentration';
    public const RULE_REBUILD_PIPELINE = 'RebuildCommercialPipeline';
    public const RULE_RESTORE_BILLING = 'RestoreBillingMomentum';
    public const RULE_PRIMARY_ATTENTION = 'AddressPrimaryAttention';

    public const RISK_OVERDUE = 'OverdueExposureRisk';
    public const RISK_CONCENTRATION = 'ClientConcentrationRisk';
    public const RISK_COMMERCIAL = 'CommercialMomentumRisk';
    public const RISK_BILLING = 'BillingMomentumRisk';

    public const FACTOR_COMMERCIAL = 'CommercialMomentum';
    public const FACTOR_BILLING = 'BillingMomentum';
    public const FACTOR_RECEIVABLES = 'ReceivablesDiscipline';
    public const FACTOR_DIVERSIFICATION = 'ClientDiversification';
}
