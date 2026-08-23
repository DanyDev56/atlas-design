<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

enum RecurringBillingEventType: string
{
    case Activated = 'subscription.activated';
    case Renewed = 'subscription.renewed';
    case PaymentFailed = 'subscription.payment_failed';
    case Canceled = 'subscription.canceled';
}
