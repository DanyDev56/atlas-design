<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Domain;

enum BillingInterval: string
{
    case Monthly = 'Monthly';
    case Annual = 'Annual';
}
