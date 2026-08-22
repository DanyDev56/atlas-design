<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Domain;

final class MetricKeys
{
    public const PIPELINE_OPEN_AMOUNT = 'analytics.pipeline.open-amount';

    public const QUOTES_PENDING_AMOUNT = 'analytics.quotes.pending-amount';

    public const QUOTES_ACCEPTANCE_RATE = 'analytics.quotes.acceptance-rate';

    public const QUOTES_AVERAGE_RESPONSE_TIME = 'analytics.quotes.average-response-time';

    public const BILLING_NET_INVOICED_AMOUNT = 'analytics.billing.net-invoiced-amount';

    public const BILLING_COLLECTED_AMOUNT = 'analytics.billing.collected-amount';

    public const RECEIVABLES_OUTSTANDING_AMOUNT = 'analytics.receivables.outstanding-amount';

    public const RECEIVABLES_OVERDUE_AMOUNT = 'analytics.receivables.overdue-amount';

    public const RECEIVABLES_OVERDUE_COUNT = 'analytics.receivables.overdue-count';

    public const RECEIVABLES_DUE_SOON_AMOUNT = 'analytics.receivables.due-soon-amount';

    public const PAYMENTS_AVERAGE_TIME_TO_PAYMENT = 'analytics.payments.average-time-to-payment';

    public const PAYMENTS_ON_TIME_RATE = 'analytics.payments.on-time-rate';

    public const CLIENTS_TOP_COLLECTION_SHARE = 'analytics.clients.top-collection-share';

    public const PROFILE_KEY = 'BusinessHealthBaselineV1';

    public const PROFILE_VERSION = '1.0.0';

    public const VALUE_AVAILABLE = 'Available';

    public const VALUE_NO_DATA = 'NoData';

    public const FRESHNESS_CURRENT = 'Current';

    public const FRESHNESS_LAGGING = 'Lagging';

    public const COMPLETENESS_COMPLETE = 'Complete';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::PIPELINE_OPEN_AMOUNT,
            self::QUOTES_PENDING_AMOUNT,
            self::QUOTES_ACCEPTANCE_RATE,
            self::QUOTES_AVERAGE_RESPONSE_TIME,
            self::BILLING_NET_INVOICED_AMOUNT,
            self::BILLING_COLLECTED_AMOUNT,
            self::RECEIVABLES_OUTSTANDING_AMOUNT,
            self::RECEIVABLES_OVERDUE_AMOUNT,
            self::RECEIVABLES_OVERDUE_COUNT,
            self::RECEIVABLES_DUE_SOON_AMOUNT,
            self::PAYMENTS_AVERAGE_TIME_TO_PAYMENT,
            self::PAYMENTS_ON_TIME_RATE,
            self::CLIENTS_TOP_COLLECTION_SHARE,
        ];
    }

    /** @return list<string> */
    public static function overviewKeys(): array
    {
        return [
            self::PIPELINE_OPEN_AMOUNT,
            self::QUOTES_PENDING_AMOUNT,
            self::BILLING_COLLECTED_AMOUNT,
            self::RECEIVABLES_OUTSTANDING_AMOUNT,
            self::RECEIVABLES_OVERDUE_AMOUNT,
            self::RECEIVABLES_OVERDUE_COUNT,
        ];
    }
}
