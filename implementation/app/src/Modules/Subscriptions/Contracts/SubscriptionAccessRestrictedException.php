<?php

declare(strict_types=1);

namespace Atlas\Modules\Subscriptions\Contracts;

final class SubscriptionAccessRestrictedException extends \DomainException
{
    public function __construct(public readonly EntitlementDecision $decision)
    {
        parent::__construct('Subscription access restricted.');
    }
}
