<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

use Atlas\Modules\Subscriptions\Domain\ProviderSubscriptionState;
use Atlas\Modules\Subscriptions\Domain\Subscription;

interface RecurringBillingReconciliationGateway
{
    public function inspect(Subscription $subscription): ProviderSubscriptionState;
}
