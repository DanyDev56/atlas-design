<?php

declare(strict_types=1);

namespace Atlas\Modules\Analytics\Domain;

final class SourceFactEventTypes
{
    /** @return list<string> */
    public static function all(): array
    {
        return [
            'crm.opportunity_created',
            'crm.opportunity_qualified',
            'crm.opportunity_updated',
            'crm.opportunity_lost',
            'crm.opportunity_won',
            'billing.quote_sent',
            'billing.quote_accepted',
            'billing.invoice_issued',
            'billing.invoice_overdue',
            'billing.payment_recorded',
            'billing.credit_note_issued',
            'billing.invoice_balance_changed',
        ];
    }
}
